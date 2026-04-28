<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\DTO\Fiscal\AnnualCumulByPair;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\FiscalRule;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Support\Collection;

/**
 * Agrégateur fiscal annuel à l'échelle de la flotte.
 *
 * Centralise les sommations de taxe (par véhicule, par entreprise, par
 * flotte) qui étaient dupliquées dans 4 controllers (Vehicle, Company,
 * Dashboard, Planning).
 *
 * **Note R-2024-003 (sémantique BOFiP)** : l'arrondi half-up à l'euro
 * est appliqué **une seule fois par redevable** (entreprise utilisatrice),
 * jamais par couple intermédiaire. L'aggregator somme les `*DueRaw` des
 * `PipelineResult` et arrondit en sortie. Cf. ADR-0006 § 2.
 */
final readonly class FleetFiscalAggregator
{
    public function __construct(
        private FiscalPipeline $pipeline,
        private FiscalYearContext $yearContext,
        private FiscalRuleReadRepositoryInterface $fiscalRules,
    ) {}

    /**
     * Total fiscal annuel d'un véhicule sommé sur toutes les
     * entreprises auxquelles il a été attribué.
     *
     * Le véhicule doit avoir ses `fiscalCharacteristics` actives
     * pré-chargées (sinon le pipeline déclenche une nouvelle requête
     * par appel via le repository).
     *
     * Note : sémantiquement c'est une vue « par véhicule » (utilisée
     * pour l'affichage dans la liste véhicules). L'arrondi BOFiP par
     * redevable se fait dans {@see companyAnnualTax()}.
     */
    public function vehicleAnnualTax(
        Vehicle $vehicle,
        AnnualCumulByPair $cumul,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($cumul->pairsForVehicle($vehicle->id) as $days) {
            $result = $this->pipeline->execute($this->buildContext($vehicle, $days, $days, $year));
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        return round($totalRaw, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Total fiscal annuel d'une entreprise sommé sur tous les
     * véhicules qu'elle a utilisés. **Implémente R-2024-003** : un
     * seul arrondi par redevable.
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     */
    public function companyAnnualTax(
        int $companyId,
        Collection $vehiclesById,
        AnnualCumulByPair $cumul,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            if ($pair['companyId'] !== $companyId) {
                continue;
            }
            $vehicle = $vehiclesById->get($pair['vehicleId']);
            if ($vehicle === null) {
                continue;
            }
            $result = $this->pipeline->execute(
                $this->buildContext($vehicle, $pair['days'], $pair['days'], $year),
            );
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        return round($totalRaw, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * **Coût plein année théorique** d'un véhicule : ce qu'il
     * coûterait s'il était attribué 100 % du temps à une seule
     * entreprise (sans LCD, prorata = 1.0).
     *
     * Utilisé en colonne Flotte (comparaison inter-véhicules
     * indépendamment de leur taux d'utilisation réel) et en KPI sur
     * la page Show. Pour le détail de calcul (méthode CO₂, catégorie
     * polluants, exonérations, codes règles), utiliser
     * {@see vehicleFullYearTaxBreakdown()}.
     */
    public function vehicleFullYearTax(Vehicle $vehicle, int $year): float
    {
        $daysInYear = $this->yearContext->daysInYear($year);

        $result = $this->pipeline->execute(
            $this->buildContext($vehicle, $daysInYear, $daysInYear, $year),
        );

        return round($result->co2DueRaw + $result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Détail complet du calcul du coût plein année d'un véhicule —
     * affiché dans la sidebar de la page Show pour expliquer comment
     * le total a été obtenu (méthode CO₂, catégorie polluants,
     * exonérations appliquées, codes règles).
     */
    public function vehicleFullYearTaxBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        $daysInYear = $this->yearContext->daysInYear($year);

        $result = $this->pipeline->execute(
            $this->buildContext($vehicle, $daysInYear, $daysInYear, $year),
        );

        $co2Tariff = round($result->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
        $pollutantsTariff = round($result->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);

        $vfc = $vehicle->fiscalCharacteristics->firstWhere(
            static fn ($v): bool => $v->effective_to === null,
        );

        $appliedRules = $this->fiscalRules
            ->findByCodesForYear($year, $result->appliedRuleCodes)
            ->map(static fn (FiscalRule $r): FiscalRuleListItemData => FiscalRuleListItemData::fromModel($r))
            ->values()
            ->all();

        return new VehicleFullYearTaxBreakdownData(
            co2Method: $result->co2Method,
            co2FullYearTariff: $co2Tariff,
            co2Explanation: $this->buildCo2Explanation($vfc, $result->co2Method, $co2Tariff, $year),
            pollutantCategory: $result->pollutantCategory,
            pollutantsFullYearTariff: $pollutantsTariff,
            pollutantsExplanation: $this->buildPollutantsExplanation($vfc, $result->pollutantCategory, $pollutantsTariff),
            exemptionReasons: $result->exemptionReasons,
            appliedRuleCodes: $result->appliedRuleCodes,
            total: round($result->co2DueRaw + $result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP),
            appliedRules: $appliedRules,
        );
    }

    private function buildCo2Explanation(
        ?VehicleFiscalCharacteristics $vfc,
        HomologationMethod $method,
        float $tariff,
        int $year,
    ): string {
        if ($vfc === null) {
            return 'Tarif annuel CO₂ calculé sans caractéristiques fiscales actives.';
        }

        $value = match ($method) {
            HomologationMethod::Wltp => $vfc->co2_wltp !== null ? "{$vfc->co2_wltp} g/km (WLTP)" : 'WLTP',
            HomologationMethod::Nedc => $vfc->co2_nedc !== null ? "{$vfc->co2_nedc} g/km (NEDC)" : 'NEDC',
            HomologationMethod::Pa => $vfc->taxable_horsepower !== null ? "{$vfc->taxable_horsepower} CV (puissance administrative)" : 'PA',
        };

        // Tarif à 0 € → exonération applicable. L'utilisateur a la
        // liste des motifs dans la section « Exonérations applicables »
        // juste en-dessous, on évite donc de re-dérouler le calcul
        // (qui serait trompeur : « ... → 0 € » sans contexte).
        if ($tariff === 0.0) {
            return sprintf(
                '%s — exonérée pour ce véhicule (voir motif ci-dessous).',
                $value,
            );
        }

        return sprintf(
            '%s × barème CO₂ %d → tarif annuel %s.',
            $value,
            $year,
            number_format($tariff, 2, ',', ' ').' €',
        );
    }

    private function buildPollutantsExplanation(
        ?VehicleFiscalCharacteristics $vfc,
        PollutantCategory $category,
        float $tariff,
    ): string {
        if ($vfc === null) {
            return 'Tarif polluants calculé sans caractéristiques fiscales actives.';
        }

        $energy = $vfc->energy_source->label();
        $euro = $vfc->euro_standard?->label() ?? 'sans norme Euro renseignée';

        if ($tariff === 0.0) {
            return sprintf(
                '%s · %s → exonérée pour ce véhicule (voir motif ci-dessous).',
                $energy,
                $euro,
            );
        }

        return sprintf(
            '%s · %s → catégorie %s → tarif fixe annuel %s.',
            $energy,
            $euro,
            $category->label(),
            number_format($tariff, 2, ',', ' ').' €',
        );
    }

    /**
     * Détail du coût annuel d'un véhicule réparti par entreprise
     * utilisatrice avec **séparation CO₂ / polluants / total**. Une
     * entrée par entreprise effectivement attributaire, non triée
     * (tri par jours décroissants à la charge du consommateur).
     *
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    public function vehicleAnnualTaxBreakdownByCompany(
        Vehicle $vehicle,
        AnnualCumulByPair $cumul,
        int $year,
    ): array {
        $rows = [];
        foreach ($cumul->pairsForVehicle($vehicle->id) as $companyId => $days) {
            $result = $this->pipeline->execute(
                $this->buildContext($vehicle, $days, $days, $year),
            );

            $taxCo2 = round($result->co2DueRaw, 2, PHP_ROUND_HALF_UP);
            $taxPollutants = round($result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

            $rows[] = [
                'companyId' => $companyId,
                'days' => $days,
                'taxCo2' => $taxCo2,
                'taxPollutants' => $taxPollutants,
                'taxTotal' => round($taxCo2 + $taxPollutants, 2, PHP_ROUND_HALF_UP),
            ];
        }

        return $rows;
    }

    /**
     * Total fiscal annuel sommé sur toute la flotte (tous couples
     * véhicule × entreprise confondus). Affiché côté Dashboard ;
     * agrégat informatif (pas un montant déclaratif).
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     */
    public function fleetAnnualTax(
        Collection $vehiclesById,
        AnnualCumulByPair $cumul,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            $vehicle = $vehiclesById->get($pair['vehicleId']);
            if ($vehicle === null) {
                continue;
            }
            $result = $this->pipeline->execute(
                $this->buildContext($vehicle, $pair['days'], $pair['days'], $year),
            );
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        return round($totalRaw, 2, PHP_ROUND_HALF_UP);
    }

    private function buildContext(
        Vehicle $vehicle,
        int $daysAssignedToCompany,
        int $cumulativeDaysForPair,
        int $year,
    ): PipelineContext {
        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: $year,
            daysInYear: $this->yearContext->daysInYear($year),
            daysAssignedToCompany: $daysAssignedToCompany,
            cumulativeDaysForPair: $cumulativeDaysForPair,
        );
    }
}
