<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Fiscal\ValueObjects\DaysWindow;
use App\Fiscal\ValueObjects\FiscalSegmentBreakdown;
use App\Fiscal\ValueObjects\VfcEffectiveSegment;

/**
 * Chef d'orchestre du moteur fiscal pour les véhicules à VFC multiples
 * dans une même année (chantier dette VFC prorata journalier — ADR-0005).
 *
 * **Pourquoi** : le {@see FiscalPipeline} respecte la doctrine ADR-0006
 * (8 étapes pour 1 fiche fiscale). Quand un véhicule a plusieurs VFC
 * effectives dans l'année (correction de saisie, mise à jour CO₂…),
 * chaque période doit être tarifée avec sa propre VFC, puis sommée.
 * Cette orchestration vit ici, le pipeline reste mono-VFC.
 *
 * **Sémantique de la segmentation** :
 *   - 0 segment → throw (véhicule sans VFC active sur l'année).
 *   - 1 segment couvrant toute l'année → exécution simple, équivalent
 *     à un appel direct au pipeline (perf : pas de window inutile).
 *   - N segments → pour chacun, exécute une sous-pipeline avec la VFC
 *     du segment + une {@see DaysWindow} qui filtre les jours présents
 *     dans R-2024-002. Les contrats restent passés **entiers** (non
 *     clippés) : R-2024-021 LCD juge sur la durée totale du contrat,
 *     pas sur la portion clippée à un segment.
 *
 * **Cas du gap entre 2 VFC** (effective_to v1 + 1 jour < effective_from v2) :
 * les jours dans le gap n'apparaissent dans aucun segment, donc ne sont
 * pas comptés. C'est cohérent avec la sémantique fiscale (un véhicule
 * sans VFC à un instant t n'est pas calculable à cet instant).
 *
 * **Statut chantier** : L1 livre l'orchestrateur seul ; les
 * consommateurs ({@see App\Services\Fiscal\FleetFiscalAggregator})
 * continuent d'appeler le pipeline directement tant que L2 n'est pas
 * livré. Conséquence : pas de régression sur les véhicules mono-VFC,
 * mais les véhicules multi-VFC restent calculés avec la VFC actuelle
 * en attendant L2.
 */
final readonly class VfcSegmentedFiscalExecutor
{
    public function __construct(
        private VehicleFiscalCharacteristicsReadRepositoryInterface $repository,
        private FiscalPipeline $pipeline,
    ) {}

    public function execute(PipelineContext $context): PipelineResult
    {
        $breakdowns = $this->executeWithSegments($context);

        if (count($breakdowns) === 1) {
            return $breakdowns[0]->result;
        }

        return $this->mergeResults(array_map(
            static fn (FiscalSegmentBreakdown $b): PipelineResult => $b->result,
            $breakdowns,
        ));
    }

    /**
     * Variante de {@see execute()} qui retourne le détail par segment
     * (VFC + résultat partiel du pipeline) au lieu du résultat fusionné.
     *
     * Utilisée par les consommateurs qui doivent exposer un calcul
     * tarifaire **par segment** dans leur DTO de présentation
     * (ex. {@see App\Services\Fiscal\FleetFiscalAggregator::vehicleFullYearTaxBreakdown()}).
     *
     * @return non-empty-list<FiscalSegmentBreakdown>
     */
    public function executeWithSegments(PipelineContext $context): array
    {
        $segments = $this->repository->findEffectiveSegmentsForYear(
            $context->vehicle,
            $context->fiscalYear,
        );

        if ($segments === []) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        $singleCoversYear = $this->isSingleSegmentCoveringYear($segments, $context->fiscalYear);

        $breakdowns = [];
        foreach ($segments as $segment) {
            $segmentContext = $context->withCurrentFiscalCharacteristics($segment->vfc);
            // En mode mono-segment couvrant l'année entière, pas de
            // window utile (perf : on évite le filtrage inutile dans
            // R-2024-002 alors que tous les jours sont conservés).
            if (! $singleCoversYear) {
                $segmentContext = $segmentContext->withDaysWindow(
                    new DaysWindow($segment->start, $segment->end),
                );
            }
            $breakdowns[] = new FiscalSegmentBreakdown(
                segment: $segment,
                result: $this->pipeline->execute($segmentContext),
            );
        }

        return $breakdowns;
    }

    /**
     * @param  list<VfcEffectiveSegment>  $segments
     */
    private function isSingleSegmentCoveringYear(array $segments, int $fiscalYear): bool
    {
        if (count($segments) !== 1) {
            return false;
        }

        $only = $segments[0];

        return $only->start->month === 1 && $only->start->day === 1
            && $only->end->month === 12 && $only->end->day === 31
            && $only->start->year === $fiscalYear
            && $only->end->year === $fiscalYear;
    }

    /**
     * Fusion des résultats partiels (1 par segment VFC).
     *
     * Règles :
     *   - `daysAssigned`, `cumulativeDaysForPair` : somme.
     *   - `co2DueRaw`, `pollutantsDueRaw` : somme (raw, avant arrondi).
     *   - `co2Due`, `pollutantsDue`, `totalDue` : recalculés par
     *     `round(somme_raw, 2, HALF_UP)` ; `totalDue = round(co2 +
     *     pollutants, 2, HALF_UP)`. Cohérent avec
     *     {@see FiscalPipeline::buildResult()}.
     *   - `co2Method`, `pollutantCategory`, tariffs : pris du premier
     *     segment (L2 exposera la liste segmentée au DTO breakdown).
     *   - flags exemption (`lcdExempt`, `electricExempt`,
     *     `handicapExempt`) : OR logique.
     *   - `appliedExemptions` : union dédupliquée par `ruleCode`.
     *   - `appliedRuleCodes` : union dédupliquée.
     *
     * @param  non-empty-list<PipelineResult>  $partials
     */
    private function mergeResults(array $partials): PipelineResult
    {
        $first = $partials[0];

        $daysAssigned = 0;
        $cumulativeDays = 0;
        $co2Raw = 0.0;
        $pollutantsRaw = 0.0;
        $lcdExempt = false;
        $electricExempt = false;
        $handicapExempt = false;
        /** @var array<string, AppliedExemption> $exemptionsByCode */
        $exemptionsByCode = [];
        /** @var array<string, true> $ruleCodesSet */
        $ruleCodesSet = [];

        foreach ($partials as $partial) {
            $daysAssigned += $partial->daysAssigned;
            $cumulativeDays += $partial->cumulativeDaysForPair;
            $co2Raw += $partial->co2DueRaw;
            $pollutantsRaw += $partial->pollutantsDueRaw;
            $lcdExempt = $lcdExempt || $partial->lcdExempt;
            $electricExempt = $electricExempt || $partial->electricExempt;
            $handicapExempt = $handicapExempt || $partial->handicapExempt;
            foreach ($partial->appliedExemptions as $exemption) {
                $exemptionsByCode[$exemption->ruleCode] ??= $exemption;
            }
            foreach ($partial->appliedRuleCodes as $ruleCode) {
                $ruleCodesSet[$ruleCode] = true;
            }
        }

        $co2Due = round($co2Raw, 2, PHP_ROUND_HALF_UP);
        $pollutantsDue = round($pollutantsRaw, 2, PHP_ROUND_HALF_UP);
        $totalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

        return new PipelineResult(
            daysAssigned: $daysAssigned,
            cumulativeDaysForPair: $cumulativeDays,
            daysInYear: $first->daysInYear,
            lcdExempt: $lcdExempt,
            electricExempt: $electricExempt,
            handicapExempt: $handicapExempt,
            co2Method: $first->co2Method,
            co2FullYearTariff: $first->co2FullYearTariff,
            co2Due: $co2Due,
            co2DueRaw: $co2Raw,
            pollutantCategory: $first->pollutantCategory,
            pollutantsFullYearTariff: $first->pollutantsFullYearTariff,
            pollutantsDue: $pollutantsDue,
            pollutantsDueRaw: $pollutantsRaw,
            totalDue: $totalDue,
            appliedExemptions: array_values($exemptionsByCode),
            appliedRuleCodes: array_keys($ruleCodesSet),
        );
    }
}
