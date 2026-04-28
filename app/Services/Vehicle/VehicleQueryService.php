<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Vehicle\VehicleCompanyUsageData;
use App\Data\User\Vehicle\VehicleData;
use App\Data\User\Vehicle\VehicleListItemData;
use App\Data\User\Vehicle\VehicleOptionData;
use App\Data\User\Vehicle\VehicleUsageStatsData;
use App\Models\Vehicle;
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Spatie\LaravelData\DataCollection;

/**
 * Orchestration des lectures du domaine Vehicle vers les DTOs exposés.
 *
 * Aucune query Eloquent ici — toutes les lectures passent par les
 * repositories. Le service combine repository + aggregator fiscal +
 * mapping DTO (R3 d'ADR-0013).
 */
final class VehicleQueryService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly AssignmentQueryService $assignments,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
    ) {}

    /**
     * Liste des véhicules pour la page « Flotte » avec **coût plein
     * année théorique** (max si véhicule attribué 100 % à 1 entreprise)
     * + pro-rata journalier équivalent.
     *
     * @return DataCollection<int, VehicleListItemData>
     */
    public function listForFleetView(int $year): DataCollection
    {
        $daysInYear = $this->yearContext->daysInYear($year);

        $rows = $this->vehicles->findAllForFleetView()
            ->map(function (Vehicle $v) use ($year, $daysInYear): VehicleListItemData {
                $fullYearTax = $this->aggregator->vehicleFullYearTax($v, $year);

                return new VehicleListItemData(
                    id: $v->id,
                    licensePlate: $v->license_plate,
                    brand: $v->brand,
                    model: $v->model,
                    currentStatus: $v->current_status,
                    firstFrenchRegistrationDate: $v->first_french_registration_date->format('Y-m-d'),
                    acquisitionDate: $v->acquisition_date->format('Y-m-d'),
                    exitDate: $v->exit_date?->format('Y-m-d'),
                    fullYearTax: $fullYearTax,
                    dailyTaxRate: round($fullYearTax / $daysInYear, 2, PHP_ROUND_HALF_UP),
                );
            })
            ->values()
            ->all();

        return VehicleListItemData::collect($rows, DataCollection::class);
    }

    /**
     * Représentation complète d'un véhicule pour la page Show :
     * identité + caractéristiques fiscales actives + historique
     * antéchronologique des versions VFC + statistiques d'utilisation
     * de l'année active (KPI + breakdown par entreprise).
     *
     * Lève `ModelNotFoundException` (rendu 404 par Laravel) si l'id
     * n'existe pas.
     */
    public function findVehicleData(int $id, int $year): VehicleData
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($id);

        return VehicleData::fromModel($vehicle, $this->buildUsageStats($vehicle, $year));
    }

    /**
     * Liste pour les `<SelectInput>` (Attribution rapide, etc.).
     * Filtre les véhicules sortis (`exit_date IS NOT NULL`).
     *
     * @return DataCollection<int, VehicleOptionData>
     */
    public function listForOptions(): DataCollection
    {
        $rows = $this->vehicles->findAllForOptions()
            ->map(static fn (Vehicle $v): VehicleOptionData => new VehicleOptionData(
                id: $v->id,
                licensePlate: $v->license_plate,
                label: sprintf('%s — %s %s', $v->license_plate, $v->brand, $v->model),
            ))
            ->values()
            ->all();

        return VehicleOptionData::collect($rows, DataCollection::class);
    }

    private function buildUsageStats(Vehicle $vehicle, int $year): VehicleUsageStatsData
    {
        $daysInYear = $this->yearContext->daysInYear($year);
        $cumul = $this->assignments->loadAnnualCumul($year);

        $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany($vehicle, $cumul, $year);

        $companyIds = array_map(static fn (array $row): int => $row['companyId'], $breakdown);
        $companiesById = $this->companies->findByIdsIndexed($companyIds);

        usort(
            $breakdown,
            static fn (array $a, array $b): int => $b['days'] <=> $a['days'],
        );

        $companies = [];
        $totalDays = 0;
        $totalTax = 0.0;
        foreach ($breakdown as $row) {
            $company = $companiesById->get($row['companyId']);
            if ($company === null) {
                continue;
            }
            $companies[] = new VehicleCompanyUsageData(
                companyId: $company->id,
                shortCode: $company->short_code,
                legalName: $company->legal_name,
                color: $company->color,
                daysUsed: $row['days'],
                taxDue: $row['taxDue'],
            );
            $totalDays += $row['days'];
            $totalTax += $row['taxDue'];
        }

        $fullYearTax = $this->aggregator->vehicleFullYearTax($vehicle, $year);

        return new VehicleUsageStatsData(
            fiscalYear: $year,
            daysInYear: $daysInYear,
            daysUsedThisYear: $totalDays,
            actualTaxThisYear: round($totalTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: $fullYearTax,
            dailyTaxRate: round($fullYearTax / $daysInYear, 2, PHP_ROUND_HALF_UP),
            companies: $companies,
        );
    }
}
