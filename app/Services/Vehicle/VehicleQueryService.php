<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\Shared\YearScopeData;
use App\Data\User\Unavailability\UnavailabilityData;
use App\Data\User\Vehicle\PaginatedVehicleListData;
use App\Data\User\Vehicle\VehicleCompanyUsageData;
use App\Data\User\Vehicle\VehicleData;
use App\Data\User\Vehicle\VehicleFiscalCharacteristicsData;
use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\Data\User\Vehicle\VehicleIndexQueryData;
use App\Data\User\Vehicle\VehicleListItemData;
use App\Data\User\Vehicle\VehicleOptionData;
use App\Data\User\Vehicle\VehicleUsageStatsData;
use App\Data\User\Vehicle\VehicleWeekSegmentData;
use App\Data\User\Vehicle\VehicleWeekUsageData;
use App\Data\User\Vehicle\VehicleYearStatsData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Company;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
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
        private readonly UnavailabilityReadRepositoryInterface $unavailabilityRepo,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
    ) {}

    /**
     * Index Vehicles paginé server-side (cf. ADR-0020).
     *
     * Le repo gère pagination + filtres `includeExited`/`status` + search
     * en SQL pur. Le service calcule ensuite `fullYearTax` + `dailyTaxRate`
     * uniquement pour les véhicules de la page courante.
     *
     * Cf. ADR-0020 D6 : le tri par `fullYearTax` est volontairement
     * absent de la whitelist sortKey (valeur calculée non SQL).
     */
    public function listPaginated(VehicleIndexQueryData $query, int $year): PaginatedVehicleListData
    {
        $daysInYear = $this->yearContext->daysInYear($year);
        $paginator = $this->vehicles->paginateForIndex($query);

        $items = array_map(
            fn (Vehicle $v): VehicleListItemData => $this->mapVehicleToListItem($v, $year, $daysInYear),
            $paginator->items(),
        );

        return new PaginatedVehicleListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    private function mapVehicleToListItem(Vehicle $v, int $year, int $daysInYear): VehicleListItemData
    {
        // Tolère une année hors registry fiscal (cohérent doctrine
        // « données métier ⊥ règles fiscales » Phase 2) : si le pipeline
        // fiscal n'a pas de règles pour `$year`, on affiche `0 €` plutôt
        // que de crasher tout l'Index.
        try {
            $fullYearTax = $this->aggregator->vehicleFullYearTax($v, $year);
        } catch (FiscalCalculationException) {
            $fullYearTax = 0.0;
        }

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
            // Placeholder V1.2 (cf. roadmap_v12_facturation) : la colonne
            // « Prix location » est exposée dès maintenant pour stabiliser
            // le contrat DTO/UI, mais reste null tant que le module
            // facturation n'est pas livré.
            rentalPriceFullYear: null,
        );
    }

    /**
     * Représentation complète d'un véhicule pour la page Show :
     * identité + caractéristiques fiscales actives + historique
     * antéchronologique des versions VFC + statistiques d'utilisation.
     *
     * **Doctrine temporelle (chantier η Phase 2)** : 3 lentilles distinctes.
     *   - Présent (`kpiYear` + `kpiStats`) : année calendaire courante,
     *     non mutable depuis l'UI.
     *   - Évolution (`history[]`) : `[minYear..kpiYear-1]`, lignes neutres
     *     (zéros) comprises pour les années sans contrat sur le véhicule.
     *   - Exploration (`usageStats` + `selectedYear`) : pilotée par le
     *     sélecteur d'année partagé (Timeline + Breakdown + FullYearTax).
     *
     * Lève `ModelNotFoundException` (rendu 404 par Laravel) si l'id
     * n'existe pas.
     */
    public function findVehicleData(int $id): VehicleData
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

        // Présent — KPI sur l'année calendaire courante.
        $kpiYear = $this->availableYears->currentYear();
        $kpiStats = $this->computeVehicleYearStats($vehicle, $kpiYear, $unavailabilityModels);
        $kpiFiscalAvailable = in_array(
            $kpiYear,
            $this->fiscalRules->registeredYears(),
            true,
        );

        // Évolution — couvre `[minYear..kpiYear-1]`, lignes neutres
        // pour les années sans contrat (cohérent avec Phase 1 Company).
        $history = [];
        $minYear = $this->availableYears->minYear();
        for ($year = $kpiYear - 1; $year >= $minYear; $year--) {
            $history[] = $this->computeVehicleYearStats($vehicle, $year, $unavailabilityModels);
        }

        // Exploration — `usageStats` initialisé sur `currentYear`. Les
        // autres années sont fetchées à la demande côté front via les
        // endpoints lazy `usageStatsForYear` / `fullYearBreakdownForYear`
        // avec cache client (composable `useYearLazy`). Évite le pré-calcul
        // backend pour des années qui ne seront jamais consultées.
        $initialYear = $kpiYear;

        return VehicleData::fromModel(
            $vehicle,
            $this->buildUsageStats($vehicle, $initialYear, $unavailabilityModels),
            $unavailabilityDtos,
            $this->buildBusyDates($vehicle->id, $initialYear),
            kpiYear: $kpiYear,
            kpiStats: $kpiStats,
            kpiFiscalAvailable: $kpiFiscalAvailable,
            history: $history,
            selectedYear: $initialYear,
            yearScope: YearScopeData::fromResolver($this->availableYears),
        );
    }

    /**
     * Endpoint lazy : recalcule `VehicleUsageStatsData` pour une année
     * arbitraire du scope. Appelé par `useYearLazy` côté front quand
     * l'utilisateur change l'année du sélecteur de la carte
     * Utilisation & Répartition (onglet Vue d'ensemble).
     *
     * Tolère une année sans règles fiscales codées : Timeline et jours
     * bruts intacts, chiffres taxes à 0 + breakdown FullYear neutre.
     */
    public function usageStatsForYear(int $vehicleId, int $year): VehicleUsageStatsData
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($vehicleId);
        $unavailabilityModels = $this->unavailabilityRepo->findForVehicle($vehicle->id);

        return $this->buildUsageStats($vehicle, $year, $unavailabilityModels);
    }

    /**
     * Endpoint lazy : recalcule `VehicleFullYearTaxBreakdownData` pour
     * une année arbitraire du scope. Appelé par l'onglet Fiscalité au
     * changement d'année du sélecteur dédié.
     *
     * Si l'année n'a pas de règles fiscales codées, retourne un DTO
     * neutre (tarifs 0 + message « Règles non implémentées »).
     */
    public function fullYearBreakdownForYear(int $vehicleId, int $year): VehicleFullYearTaxBreakdownData
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($vehicleId);

        try {
            return $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
        } catch (FiscalCalculationException) {
            return $this->emptyFullYearBreakdown($vehicle, $year);
        }
    }

    /**
     * Calcule les stats annuelles d'un véhicule pour une année donnée
     * (jours utilisés, nombre de contrats, taxe réelle, coût plein).
     * Utilisé pour les KPI Présent et chaque ligne de history.
     *
     * Tolère l'absence de configuration fiscale sur l'année : retourne
     * `actualTax = 0` et `fullYearTax = 0` plutôt que de crasher la page
     * — l'utilisateur voit quand même les jours et le compte de contrats.
     *
     * @param  Collection<int, Unavailability>  $unavailabilityModels  Indispos pré-chargées (toutes années).
     */
    private function computeVehicleYearStats(
        Vehicle $vehicle,
        int $year,
        Collection $unavailabilityModels,
    ): VehicleYearStatsData {
        $contractsByPair = $this->contracts->loadContractsByPairForVehicle($vehicle->id, $year);
        $vehicleUnavailabilities = $unavailabilityModels->all();

        $daysUsed = 0;
        $contractsCount = 0;
        foreach ($contractsByPair->pairsForVehicle($vehicle->id) as $pairContracts) {
            foreach ($pairContracts as $contract) {
                $daysUsed += count($contract->expandToDaysInYear($year));
                $contractsCount++;
            }
        }

        $actualTax = 0.0;
        $fullYearTax = 0.0;
        try {
            $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
                $vehicle,
                $contractsByPair,
                $vehicleUnavailabilities,
                $year,
            );
            foreach ($breakdown as $row) {
                $actualTax += (float) $row['taxTotal'];
            }
            $fullYearTax = $this->aggregator->vehicleFullYearTax($vehicle, $year);
        } catch (FiscalCalculationException) {
            // Année hors registry fiscal — chiffres taxes laissés à 0.
        }

        return new VehicleYearStatsData(
            year: $year,
            daysUsed: $daysUsed,
            contractsCount: $contractsCount,
            actualTax: round($actualTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: round($fullYearTax, 2, PHP_ROUND_HALF_UP),
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
     * Bornes min/max d'année de 1ʳᵉ immatriculation parmi tous les
     * véhicules en BDD. Alimente le sélecteur de fourchette du filtre
     * Index. Retourne `null` si la flotte est vide (le frontend cache
     * alors le filtre).
     *
     * @return array{min: int, max: int}|null
     */
    public function firstRegistrationYearBounds(): ?array
    {
        return $this->vehicles->findFirstRegistrationYearBounds();
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

        // Calcul fiscal — encadré pour tolérer une année hors registry
        // (doctrine « données métier ⊥ règles fiscales » : la Timeline et
        // les jours bruts restent toujours affichables, seuls les
        // chiffres de taxe tombent à 0 + breakdown FullYear neutre).
        try {
            $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
                $vehicle,
                $contractsByPair,
                $vehicleUnavailabilities,
                $year,
            );
            $fullYearBreakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
        } catch (FiscalCalculationException) {
            $breakdown = $this->fallbackBreakdownByCompany($contractsByPair, $vehicle->id);
            $fullYearBreakdown = $this->emptyFullYearBreakdown($vehicle, $year);
        }

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

        return new VehicleUsageStatsData(
            fiscalYear: $year,
            daysInYear: $daysInYear,
            daysUsedThisYear: $totalDays,
            actualTaxThisYear: round($totalTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: $fullYearBreakdown->total,
            dailyTaxRate: $daysInYear > 0
                ? round($fullYearBreakdown->total / $daysInYear, 2, PHP_ROUND_HALF_UP)
                : 0.0,
            companies: $companies,
            weeklyBreakdown: $this->buildWeeklyBreakdown($weeklyMap, $unavailabilityDaysByWeek, $companiesById, $year),
            fullYearTaxBreakdown: $fullYearBreakdown,
        );
    }

    /**
     * Compose un breakdown par entreprise à partir des contrats seuls
     * (jours uniquement, sans calcul fiscal). Utilisé en fallback quand
     * le pipeline fiscal n'est pas disponible pour l'année — la colonne
     * « Jours » reste informative, les colonnes Tax CO₂/Polluants/Total
     * sont à 0.
     *
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    private function fallbackBreakdownByCompany(ContractsByPair $contractsByPair, int $vehicleId): array
    {
        $rows = [];
        foreach ($contractsByPair->pairsForVehicle($vehicleId) as $companyId => $pairContracts) {
            $days = 0;
            foreach ($pairContracts as $contract) {
                $days += count($contract->expandToDaysInYear($contract->start_date->year));
            }
            $rows[] = [
                'companyId' => $companyId,
                'days' => $days,
                'taxCo2' => 0.0,
                'taxPollutants' => 0.0,
                'taxTotal' => 0.0,
            ];
        }

        return $rows;
    }

    /**
     * DTO `VehicleFullYearTaxBreakdownData` neutre — tarifs à 0 et
     * messages explicites pour l'UI quand l'année n'a pas de règles
     * fiscales codées. Les enums `co2Method` / `pollutantCategory` sont
     * pris du current VFC du véhicule (ou défaut WLTP/Category1).
     */
    private function emptyFullYearBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        $current = $vehicle->fiscalCharacteristics
            ->firstWhere(static fn ($vfc): bool => $vfc->effective_to === null);

        $message = sprintf('Règles fiscales %d non implémentées.', $year);

        return new VehicleFullYearTaxBreakdownData(
            co2Method: $current !== null ? $current->homologation_method : HomologationMethod::Wltp,
            co2FullYearTariff: 0.0,
            co2Explanation: $message,
            pollutantCategory: $current !== null ? $current->pollutant_category : PollutantCategory::Category1,
            pollutantsFullYearTariff: 0.0,
            pollutantsExplanation: $message,
            appliedExemptions: [],
            appliedRuleCodes: [],
            total: 0.0,
            appliedRules: [],
            appliedVfc: $current !== null
                ? VehicleFiscalCharacteristicsData::fromModel($current)
                : null,
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

        return array_keys($ids);
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
