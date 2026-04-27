<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\DTO\Fiscal\AnnualCumulByPair;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Agrégateur fiscal annuel à l'échelle de la flotte.
 *
 * Centralise les sommations de taxe (par véhicule, par entreprise, par
 * flotte) qui étaient dupliquées dans 4 controllers (Vehicle, Company,
 * Dashboard, Planning). Toutes les méthodes attendent un
 * {@see AnnualCumulByPair} et une `Collection<int, Vehicle>` indexée
 * par id pour éviter tout N+1.
 */
final class FleetFiscalAggregator
{
    public function __construct(private readonly FiscalCalculator $calculator) {}

    /**
     * Total fiscal annuel d'un véhicule sommé sur toutes les
     * entreprises auxquelles il a été attribué.
     *
     * Le véhicule doit avoir ses `fiscalCharacteristics` actives
     * pré-chargées (sinon le calculator déclenche une nouvelle
     * requête par appel).
     */
    public function vehicleAnnualTax(
        Vehicle $vehicle,
        AnnualCumulByPair $cumul,
        int $year,
    ): float {
        $total = 0.0;
        foreach ($cumul->pairsForVehicle($vehicle->id) as $days) {
            $total += $this->calculator
                ->calculate($vehicle, $days, $days, $year)
                ->totalDue;
        }

        return round($total, 2);
    }

    /**
     * Total fiscal annuel d'une entreprise sommé sur tous les
     * véhicules qu'elle a utilisés.
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     */
    public function companyAnnualTax(
        int $companyId,
        Collection $vehiclesById,
        AnnualCumulByPair $cumul,
        int $year,
    ): float {
        $total = 0.0;
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            if ($pair['companyId'] !== $companyId) {
                continue;
            }
            $vehicle = $vehiclesById->get($pair['vehicleId']);
            if ($vehicle === null) {
                continue;
            }
            $total += $this->calculator
                ->calculate($vehicle, $pair['days'], $pair['days'], $year)
                ->totalDue;
        }

        return round($total, 2);
    }

    /**
     * Total fiscal annuel sommé sur toute la flotte (tous couples
     * véhicule × entreprise confondus).
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     */
    public function fleetAnnualTax(
        Collection $vehiclesById,
        AnnualCumulByPair $cumul,
        int $year,
    ): float {
        $total = 0.0;
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            $vehicle = $vehiclesById->get($pair['vehicleId']);
            if ($vehicle === null) {
                continue;
            }
            $total += $this->calculator
                ->calculate($vehicle, $pair['days'], $pair['days'], $year)
                ->totalDue;
        }

        return round($total, 2);
    }
}
