<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\User\Dashboard\DashboardStatsData;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\FiscalRule;
use App\Models\Vehicle;
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Calcul des KPIs de la page Dashboard.
 *
 * Tire parti du même `AnnualCumulByPair` que les autres services
 * et précharge en une requête tous les véhicules concernés pour
 * éviter les N+1.
 */
final class DashboardStatsService
{
    public function __construct(
        private readonly AssignmentQueryService $assignments,
        private readonly FleetFiscalAggregator $aggregator,
    ) {}

    public function computeStats(int $year): DashboardStatsData
    {
        $cumul = $this->assignments->loadAnnualCumul($year);

        // Pré-chargement bulk des véhicules concernés.
        $vehicleIds = [];
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehiclesById = Vehicle::query()
            ->whereIn('id', array_keys($vehicleIds))
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->get()
            ->keyBy('id');

        return new DashboardStatsData(
            vehiclesCount: Vehicle::query()->whereNull('exit_date')->count(),
            companiesCount: Company::query()->where('is_active', true)->count(),
            assignmentsYear: Assignment::query()->whereYear('date', $year)->count(),
            fiscalRulesCount: FiscalRule::query()
                ->where('fiscal_year', $year)
                ->where('is_active', true)
                ->count(),
            totalTaxDue: $this->aggregator->fleetAnnualTax($vehiclesById, $cumul, $year),
        );
    }
}
