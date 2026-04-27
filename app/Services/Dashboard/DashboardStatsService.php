<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardStatsData;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Calcul des KPIs de la page Dashboard.
 *
 * Tire parti du même `AnnualCumulByPair` que les autres services et
 * précharge en bulk via le repository tous les véhicules concernés
 * pour éviter les N+1.
 */
final class DashboardStatsService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly AssignmentReadRepositoryInterface $assignments,
        private readonly FiscalRuleReadRepositoryInterface $fiscalRules,
        private readonly FleetFiscalAggregator $aggregator,
    ) {}

    public function computeStats(int $year): DashboardStatsData
    {
        $cumul = $this->assignments->loadAnnualCumul($year);

        $vehicleIds = [];
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehiclesById = $this->vehicles->findByIdsIndexed(array_keys($vehicleIds));

        return new DashboardStatsData(
            vehiclesCount: $this->vehicles->countActive(),
            companiesCount: $this->companies->countActive(),
            assignmentsYear: $this->assignments->countForYear($year),
            fiscalRulesCount: $this->fiscalRules->countActiveForYear($year),
            totalTaxDue: $this->aggregator->fleetAnnualTax($vehiclesById, $cumul, $year),
        );
    }
}
