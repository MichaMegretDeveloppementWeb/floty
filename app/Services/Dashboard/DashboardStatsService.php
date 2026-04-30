<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardStatsData;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Calcul des KPIs de la page Dashboard.
 *
 * KPIs dérivés des contrats (ADR-0014). `contractDaysYear` est le cumul
 * des jours-contrat occupés sur l'année fiscale.
 */
final class DashboardStatsService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly ContractQueryService $contracts,
        private readonly FiscalRuleReadRepositoryInterface $fiscalRules,
        private readonly FleetFiscalAggregator $aggregator,
    ) {}

    public function computeStats(int $year): DashboardStatsData
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);

        $vehicleIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehicleIdList = array_keys($vehicleIds);
        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);

        return new DashboardStatsData(
            vehiclesCount: $this->vehicles->countActive(),
            companiesCount: $this->companies->countActive(),
            contractDaysYear: $this->contracts->countContractDaysForYear($year),
            fiscalRulesCount: $this->fiscalRules->countActiveForYear($year),
            totalTaxDue: $this->aggregator->fleetAnnualTax(
                $vehiclesById,
                $contractsByPair,
                $unavailabilitiesByVehicleId,
                $year,
            ),
        );
    }
}
