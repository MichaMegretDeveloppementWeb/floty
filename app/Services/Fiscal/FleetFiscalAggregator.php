<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\DTO\Fiscal\AnnualCumulByPair;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\Vehicle;
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

        return new VehicleFullYearTaxBreakdownData(
            co2Method: $result->co2Method,
            co2FullYearTariff: $co2Tariff,
            pollutantCategory: $result->pollutantCategory,
            pollutantsFullYearTariff: $pollutantsTariff,
            exemptionReasons: $result->exemptionReasons,
            appliedRuleCodes: $result->appliedRuleCodes,
            total: round($result->co2DueRaw + $result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP),
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
