<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Unavailability\UnavailabilityData;
use App\Data\User\Vehicle\VehicleCompanyUsageData;
use App\Data\User\Vehicle\VehicleData;
use App\Data\User\Vehicle\VehicleListItemData;
use App\Data\User\Vehicle\VehicleOptionData;
use App\Data\User\Vehicle\VehicleUsageStatsData;
use App\Data\User\Vehicle\VehicleWeekSegmentData;
use App\Data\User\Vehicle\VehicleWeekUsageData;
use App\Models\Company;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use App\Services\Unavailability\UnavailabilityQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Orchestration des lectures du domaine Vehicle vers les DTOs exposés.
 *
 * Aucune query Eloquent ici - toutes les lectures passent par les
 * repositories. Le service combine repository + aggregator fiscal +
 * mapping DTO (R3 d'ADR-0013).
 *
 * **Refonte 04.F (ADR-0014)** : la source des cumuls fiscaux et de la
 * timeline hebdomadaire est `ContractQueryService` (l'ancien domaine
 * Assignment a été supprimé en chantier 04.H).
 */
final class VehicleQueryService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
        private readonly UnavailabilityQueryService $unavailabilities,
        private readonly UnavailabilityReadRepositoryInterface $unavailabilityRepo,
    ) {}

    /**
     * Liste des véhicules pour la page « Flotte » avec **coût plein
     * année théorique** (max si véhicule attribué 100 % à 1 entreprise)
     * + pro-rata journalier équivalent.
     *
     * @return DataCollection<int, VehicleListItemData>
     */
    public function listForFleetView(int $year, bool $includeExited = false): DataCollection
    {
        $daysInYear = $this->yearContext->daysInYear($year);

        $rows = $this->vehicles->findAllForFleetView($includeExited)
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
                    exitReason: $v->exit_reason,
                    isExited: $v->is_exited,
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

        // Charge la Collection brute UNE fois et la propage à
        // `buildUsageStats` + à la composition des DTO de la timeline
        // - auparavant la même requête `findForVehicle` partait deux
        // fois (via `UnavailabilityQueryService` puis re-direct repo).
        $unavailabilityModels = $this->unavailabilityRepo->findForVehicle($vehicle->id);
        $unavailabilityDtos = $unavailabilityModels
            ->map(static fn (Unavailability $u): UnavailabilityData => UnavailabilityData::fromModel($u))
            ->values()
            ->all();

        return VehicleData::fromModel(
            $vehicle,
            $this->buildUsageStats($vehicle, $year, $unavailabilityModels),
            $unavailabilityDtos,
            $this->buildBusyDates($vehicle->id, $year),
        );
    }

    /**
     * Liste pour les `<SelectInput>` des formulaires (drawer Contrats,
     * etc.). Inclut les véhicules sortis pour permettre la consultation
     * et l'édition rétroactive des contrats antérieurs (cf. ADR-0018 § 4).
     *
     * Le frontend distingue actifs/retirés via `isExited` (groupement
     * dans le picker, suffixe label « (retiré le DD/MM/YYYY) »).
     *
     * @return DataCollection<int, VehicleOptionData>
     */
    public function listForOptions(): DataCollection
    {
        $rows = $this->vehicles->findAllForOptions()
            ->map(static function (Vehicle $v): VehicleOptionData {
                $exitDate = $v->exit_date?->format('Y-m-d');
                $label = sprintf('%s - %s %s', $v->license_plate, $v->brand, $v->model);

                return new VehicleOptionData(
                    id: $v->id,
                    licensePlate: $v->license_plate,
                    label: $label,
                    isExited: $exitDate !== null,
                    exitDate: $exitDate,
                );
            })
            ->values()
            ->all();

        return VehicleOptionData::collect($rows, DataCollection::class);
    }

    /**
     * @param  Collection<int, Unavailability>  $unavailabilityModels  indispos brutes du véhicule (toutes années) - chargées une seule fois en amont
     */
    private function buildUsageStats(Vehicle $vehicle, int $year, Collection $unavailabilityModels): VehicleUsageStatsData
    {
        $daysInYear = $this->yearContext->daysInYear($year);
        $contractsByPair = $this->contracts->loadContractsByPairForVehicle($vehicle->id, $year);
        $vehicleUnavailabilities = $unavailabilityModels->all();
        $weeklyMap = $this->contracts->loadVehicleWeeklyBreakdown($vehicle->id, $year);
        $unavailabilityDaysByWeek = $this->computeUnavailabilityDaysByWeek($unavailabilityModels, $year);

        $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            $contractsByPair,
            $vehicleUnavailabilities,
            $year,
        );

        $companyIds = $this->collectCompanyIds($breakdown, $weeklyMap);
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
            $proratoPercent = $daysInYear > 0
                ? round($row['days'] / $daysInYear * 100, 1)
                : 0.0;

            $companies[] = new VehicleCompanyUsageData(
                companyId: $company->id,
                shortCode: $company->short_code,
                legalName: $company->legal_name,
                color: $company->color,
                daysUsed: $row['days'],
                proratoPercent: $proratoPercent,
                taxCo2: $row['taxCo2'],
                taxPollutants: $row['taxPollutants'],
                taxTotal: $row['taxTotal'],
            );
            $totalDays += $row['days'];
            $totalTax += $row['taxTotal'];
        }

        $fullYearBreakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);

        return new VehicleUsageStatsData(
            fiscalYear: $year,
            daysInYear: $daysInYear,
            daysUsedThisYear: $totalDays,
            actualTaxThisYear: round($totalTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: $fullYearBreakdown->total,
            dailyTaxRate: round($fullYearBreakdown->total / $daysInYear, 2, PHP_ROUND_HALF_UP),
            companies: $companies,
            weeklyBreakdown: $this->buildWeeklyBreakdown($weeklyMap, $unavailabilityDaysByWeek, $companiesById, $year),
            fullYearTaxBreakdown: $fullYearBreakdown,
        );
    }

    /**
     * Collecte tous les companyIds référencés par le breakdown
     * fiscal et la timeline hebdo. Garantit qu'aucune lookup
     * Eloquent n'est manquante (ex. semaine seedée mais 0 jour
     * cumul filtré par fourrière).
     *
     * @param  list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>  $breakdown
     * @param  array<int, array<int, int>>  $weeklyMap
     * @return list<int>
     */
    private function collectCompanyIds(array $breakdown, array $weeklyMap): array
    {
        $ids = [];
        foreach ($breakdown as $row) {
            $ids[$row['companyId']] = true;
        }
        foreach ($weeklyMap as $companies) {
            foreach (array_keys($companies) as $companyId) {
                $ids[$companyId] = true;
            }
        }

        return array_values(array_keys($ids));
    }

    /**
     * Liste flat des dates ISO (Y-m-d) déjà occupées par un contrat
     * actif sur le véhicule pour l'année. Alimente le `DateRangePicker`
     * du modal indispos pour griser les jours non-sélectionnables.
     *
     * Bornée à `[01-01-Y, 31-12-Y]` - les contrats hors fenêtre ne
     * bloquent pas l'UI (l'Action vérifie de toute façon avant écriture
     * si l'utilisateur ouvre une plage débordante).
     *
     * @return list<string>
     */
    private function buildBusyDates(int $vehicleId, int $year): array
    {
        return $this->contracts->findDatesForVehicleInRange(
            $vehicleId,
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
        );
    }

    /**
     * Compose la liste des 52-53 entrées weekly (1 par semaine ISO
     * de l'année) pour la timeline visuelle. Les semaines vides
     * sont matérialisées avec `segments = []` et `totalDays = 0`.
     *
     * Le `unavailabilityDays` est borné à `7 - totalDays` pour éviter
     * un dépassement visuel (cas exceptionnel d'une indispo et d'un
     * contrat sur le même jour - la base interdit normalement ce
     * scénario via le check overlap des Actions).
     *
     * @param  array<int, array<int, int>>  $weeklyMap  weekNumber → companyId → days
     * @param  array<int, int>  $unavailabilityDaysByWeek  weekNumber → jours d'indispo
     * @param  Collection<int, Company>  $companiesById
     * @return list<VehicleWeekUsageData>
     */
    private function buildWeeklyBreakdown(
        array $weeklyMap,
        array $unavailabilityDaysByWeek,
        Collection $companiesById,
        int $year,
    ): array {
        $weeksInYear = (int) Carbon::create($year, 12, 28)->isoWeeksInYear;

        $rows = [];
        for ($week = 1; $week <= $weeksInYear; $week++) {
            $segments = [];
            $totalDays = 0;
            foreach (($weeklyMap[$week] ?? []) as $companyId => $days) {
                $company = $companiesById->get($companyId);
                if ($company === null) {
                    continue;
                }
                $segments[] = new VehicleWeekSegmentData(
                    companyId: $company->id,
                    shortCode: $company->short_code,
                    color: $company->color,
                    days: $days,
                );
                $totalDays += $days;
            }
            // Tri stable par companyId pour rendu déterministe
            usort(
                $segments,
                static fn (VehicleWeekSegmentData $a, VehicleWeekSegmentData $b): int => $a->companyId <=> $b->companyId,
            );

            $unavailabilityDays = min(
                $unavailabilityDaysByWeek[$week] ?? 0,
                7 - $totalDays,
            );

            $rows[] = new VehicleWeekUsageData(
                weekNumber: $week,
                segments: $segments,
                totalDays: $totalDays,
                unavailabilityDays: max(0, $unavailabilityDays),
            );
        }

        return $rows;
    }

    /**
     * Comptage `weekNumber → jours d'indispo` à partir de la Collection
     * brute déjà chargée (port PHP de
     * `UnavailabilityReadRepository::findUnavailableDaysByWeekForVehicle`).
     *
     * Évite la requête supplémentaire qui partait sur la même table
     * `unavailabilities` que `findForVehicle` quelques lignes plus haut.
     *
     * @param  Collection<int, Unavailability>  $unavailabilityModels
     * @return array<int, int> weekNumber (1-53) → jours d'indispo (1-7)
     */
    private function computeUnavailabilityDaysByWeek(Collection $unavailabilityModels, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        /** @var array<int, array<string, bool>> $byWeekDays */
        $byWeekDays = [];
        foreach ($unavailabilityModels as $row) {
            // Filtre indispo croisant l'année (équivalent du WHERE SQL).
            if ($row->start_date->greaterThan($yearEnd)) {
                continue;
            }
            if ($row->end_date !== null && $row->end_date->lessThan($yearStart)) {
                continue;
            }

            $start = $row->start_date->greaterThan($yearStart) ? $row->start_date : $yearStart;
            $end = $row->end_date === null || $row->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $row->end_date;

            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $week = (int) $cursor->isoWeek;
                    $byWeekDays[$week] ??= [];
                    $byWeekDays[$week][$cursor->toDateString()] = true;
                }
                $cursor = $cursor->addDay();
            }
        }

        $byWeek = [];
        foreach ($byWeekDays as $week => $days) {
            $byWeek[$week] = count($days);
        }
        ksort($byWeek);

        return $byWeek;
    }
}
