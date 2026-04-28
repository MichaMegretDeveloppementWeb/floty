<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

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
     * la page Show.
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
     * Détail du coût annuel d'un véhicule réparti par entreprise
     * utilisatrice. Une entrée par entreprise effectivement attributaire,
     * non triée (tri par jours décroissants à la charge du consommateur).
     *
     * @return list<array{companyId: int, days: int, taxDue: float}>
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
            $rows[] = [
                'companyId' => $companyId,
                'days' => $days,
                'taxDue' => round(
                    $result->co2DueRaw + $result->pollutantsDueRaw,
                    2,
                    PHP_ROUND_HALF_UP,
                ),
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
