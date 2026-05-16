<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Fiscal\ValueObjects\DaysWindow;
use App\Fiscal\ValueObjects\FiscalSegmentBreakdown;
use App\Fiscal\ValueObjects\RuleEffectiveSegment;
use App\Fiscal\ValueObjects\VfcEffectiveSegment;
use Carbon\CarbonImmutable;

/**
 * Chef d'orchestre du moteur fiscal Floty.
 *
 * Exécute le pipeline {@see FiscalPipeline} sur le **produit cartésien
 * des segments VFC × Règles** (chantier κ.4 - granularité temporelle).
 * Chaque sous-segment de l'intersection est tarifé avec :
 *   - la VFC active sur la période,
 *   - les règles applicables sur la période,
 *   - une {@see DaysWindow} qui clippe le compteur de jours présents
 *     dans R-2024-002, sans modifier la définition des contrats
 *     (R-2024-021 LCD juge sur la durée totale du contrat, indépendante
 *     du clipping).
 *
 * **Pourquoi VFC × Règles** : un véhicule peut avoir plusieurs VFC
 * dans une année (correction de saisie, mise à jour CO₂…) et les
 * règles fiscales peuvent évoluer en cours d'année (apparition,
 * disparition, modification). Le pipeline {@see FiscalPipeline} reste
 * mono-segment ; cet exécuteur orchestre l'union temporelle.
 *
 * **Sémantique de la segmentation** :
 *   - 0 segment VFC dans l'année → throw `missingFiscalCharacteristics`.
 *   - 0 segment règles dans l'année (registry vide pour cette année)
 *     → throw `noViableCalculationWindow`.
 *   - Cartésien clippé : pour chaque couple `(vfcSeg, ruleSeg)`,
 *     intersection `[max(start), min(end)]`. Si vide, couple ignoré.
 *   - Si tous les couples ont une intersection vide → throw
 *     `noViableCalculationWindow` (cas dégénéré, distinct de VFC
 *     manquante : on a la VFC mais aucune fenêtre calculable).
 *   - Court-circuit perf : si **1 seul** sous-segment résulte ET qu'il
 *     couvre exactement l'année entière, pas de DaysWindow posée
 *     (équivalent au mode mono pré-segmentation, perf : on évite le
 *     filtrage inutile dans R-2024-002).
 *
 * **No regression invariant 2024** : en 2024, toutes les règles
 * couvrent l'année entière → 1 seul segment règles. Le cartésien
 * `[N VFC × 1 règle]` produit exactement les mêmes `N` partials qu'en
 * pré-κ.4. Tous les calculs 2024 restent strictement identiques.
 *
 * **Cas du gap entre 2 VFC** : les jours dans le gap n'apparaissent
 * dans aucun segment et ne sont donc pas comptés - cohérent avec la
 * sémantique fiscale (un véhicule sans VFC à un instant t n'est pas
 * calculable à cet instant).
 */
final readonly class FiscalSegmentedExecutor
{
    public function __construct(
        private VehicleFiscalCharacteristicsReadRepositoryInterface $vfcRepository,
        private RuleEffectiveSegmenter $ruleSegmenter,
        private FiscalPipeline $pipeline,
    ) {}

    public function execute(PipelineContext $context): PipelineResult
    {
        $vfcSegments = $this->fetchVfcSegments($context);

        return $this->buildAndMergeResult($context, $vfcSegments);
    }

    /**
     * Variante de {@see execute()} qui consomme une **liste de segments
     * VFC pré-chargée par l'appelant** au lieu d'aller en BDD. Permet
     * de collapser N+1 queries quand on calcule la taxe pleine année
     * d'un batch de véhicules (cf.
     * {@see App\Services\Fiscal\FleetFiscalAggregator::prewarmFullYearForVehicles()}).
     *
     * **Précondition stricte** · `$vfcSegments` doit être strictement
     * identique à ce que `findEffectiveSegmentsForYear($context->vehicle,
     * $context->fiscalYear)` retournerait (typiquement obtenu via
     * {@see App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface::findEffectiveSegmentsForYearBatch()}).
     *
     * **Équivalence garantie** · sous cette précondition, le résultat
     * est strictement identique à `execute($context)`. Cf. doctrine
     * `optimisations-conditionnelles.md` stratégie 2 · le test
     * `FiscalSegmentedExecutorTest::executeWithPreloadedVfcSegments_equivalent_a_execute`
     * couvre cette équivalence.
     *
     * @param  list<VfcEffectiveSegment>  $vfcSegments
     */
    public function executeWithPreloadedVfcSegments(PipelineContext $context, array $vfcSegments): PipelineResult
    {
        if ($vfcSegments === []) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        return $this->buildAndMergeResult($context, $vfcSegments);
    }

    /**
     * Variante de {@see execute()} qui retourne le détail par
     * sous-segment (intersection VFC × Règles + résultat partiel du
     * pipeline) au lieu du résultat fusionné.
     *
     * Utilisée par les consommateurs qui doivent exposer un calcul
     * tarifaire **par sous-segment** dans leur DTO de présentation
     * (ex. {@see App\Services\Fiscal\FleetFiscalAggregator::vehicleFullYearTaxBreakdown()}).
     *
     * @return non-empty-list<FiscalSegmentBreakdown>
     */
    public function executeWithSegments(PipelineContext $context): array
    {
        $vfcSegments = $this->fetchVfcSegments($context);

        return $this->buildBreakdowns($context, $vfcSegments);
    }

    /**
     * @return non-empty-list<VfcEffectiveSegment>
     */
    private function fetchVfcSegments(PipelineContext $context): array
    {
        $vfcSegments = $this->vfcRepository->findEffectiveSegmentsForYear(
            $context->vehicle,
            $context->fiscalYear,
        );

        if ($vfcSegments === []) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        return $vfcSegments;
    }

    /**
     * Helper · exécute le pipeline sur chaque sous-segment cartésien
     * VFC × Règles, fusionne en un seul `PipelineResult`. Cœur partagé
     * entre {@see execute()} et {@see executeWithPreloadedVfcSegments()}
     * pour garantir l'équivalence stricte des deux chemins (cf. doc
     * `optimisations-conditionnelles.md`).
     *
     * @param  non-empty-list<VfcEffectiveSegment>  $vfcSegments
     */
    private function buildAndMergeResult(PipelineContext $context, array $vfcSegments): PipelineResult
    {
        $breakdowns = $this->buildBreakdowns($context, $vfcSegments);

        if (count($breakdowns) === 1) {
            return $breakdowns[0]->result;
        }

        return $this->mergeResults(array_map(
            static fn (FiscalSegmentBreakdown $b): PipelineResult => $b->result,
            $breakdowns,
        ));
    }

    /**
     * Construit les sous-segments cartésiens VFC × Règles et exécute
     * le pipeline sur chacun. Extrait de l'ancien
     * `executeWithSegments()` post-refactor (chantier perf 2026-05-16
     * Option 3b).
     *
     * @param  non-empty-list<VfcEffectiveSegment>  $vfcSegments
     * @return non-empty-list<FiscalSegmentBreakdown>
     */
    private function buildBreakdowns(PipelineContext $context, array $vfcSegments): array
    {
        $ruleSegments = $this->ruleSegmenter->segmentsForYear($context->fiscalYear);

        if ($ruleSegments === []) {
            // Cas dégénéré : année déclarée dans le registry mais aucune
            // règle effective sur l'année (registry vide pour l'année).
            // Distinct de la VFC manquante : on a la VFC, mais pas de
            // règle calculable.
            throw FiscalCalculationException::noViableCalculationWindow(
                $context->vehicle->id,
                $context->fiscalYear,
            );
        }

        // Produit cartésien clippé : on garde uniquement les couples dont
        // l'intersection est non vide.
        /** @var list<array{vfc: VfcEffectiveSegment, rule: RuleEffectiveSegment, start: CarbonImmutable, end: CarbonImmutable}> $pairs */
        $pairs = [];
        foreach ($vfcSegments as $vfcSeg) {
            foreach ($ruleSegments as $ruleSeg) {
                $start = $vfcSeg->start->greaterThan($ruleSeg->start) ? $vfcSeg->start : $ruleSeg->start;
                $end = $vfcSeg->end->lessThan($ruleSeg->end) ? $vfcSeg->end : $ruleSeg->end;

                if ($start->greaterThan($end)) {
                    continue;
                }

                $pairs[] = [
                    'vfc' => $vfcSeg,
                    'rule' => $ruleSeg,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        if ($pairs === []) {
            // Le cartésien VFC × Règles a produit 0 intersection non-vide.
            // Ex. règles 2025 effectives `01/07 → 31/12` + VFC qui s'arrête
            // le 15/06/2025. Distinct de la VFC manquante.
            throw FiscalCalculationException::noViableCalculationWindow(
                $context->vehicle->id,
                $context->fiscalYear,
            );
        }

        $singleCoversYear = count($pairs) === 1
            && $this->coversFullYear($pairs[0]['start'], $pairs[0]['end'], $context->fiscalYear);

        $breakdowns = [];
        foreach ($pairs as $p) {
            $segmentContext = $context->withCurrentFiscalCharacteristics($p['vfc']->vfc);
            // En mode mono-segment couvrant l'année entière, pas de
            // window utile (perf : on évite le filtrage dans R-2024-002
            // alors que tous les jours sont conservés).
            if (! $singleCoversYear) {
                $segmentContext = $segmentContext->withDaysWindow(
                    new DaysWindow($p['start'], $p['end']),
                );
            }

            $breakdowns[] = new FiscalSegmentBreakdown(
                start: $p['start'],
                end: $p['end'],
                vfcSegment: $p['vfc'],
                ruleSegment: $p['rule'],
                result: $this->pipeline->executeWithRules($segmentContext, $p['rule']->rules),
            );
        }

        return $breakdowns;
    }

    private function coversFullYear(CarbonImmutable $start, CarbonImmutable $end, int $fiscalYear): bool
    {
        return $start->month === 1 && $start->day === 1
            && $end->month === 12 && $end->day === 31
            && $start->year === $fiscalYear
            && $end->year === $fiscalYear;
    }

    /**
     * Fusion des résultats partiels (1 par sous-segment cartésien).
     *
     * Règles :
     *   - `daysAssigned`, `cumulativeDaysForPair` : somme.
     *   - `co2DueRaw`, `pollutantsDueRaw` : somme (raw, avant arrondi).
     *   - `co2Due`, `pollutantsDue`, `totalDue` : recalculés par
     *     `round(somme_raw, 2, HALF_UP)` ; `totalDue = round(co2 +
     *     pollutants, 2, HALF_UP)`. Cohérent avec
     *     {@see FiscalPipeline::buildResult()}.
     *   - `co2Method`, `pollutantCategory`, tariffs : pris du premier
     *     sous-segment (les consommateurs UI exposent la liste
     *     segmentée dans leur DTO breakdown).
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
