<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\Shared\YearScopeData;
use App\Data\User\Company\CompanyActivityYearData;
use App\Data\User\Company\CompanyColorOptionData;
use App\Data\User\Company\CompanyDetailData;
use App\Data\User\Company\CompanyDriverRowData;
use App\Data\User\Company\CompanyFiscalYearData;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Data\User\Company\CompanyLifetimeStatsData;
use App\Data\User\Company\CompanyListItemData;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Company\CompanyTopVehicleData;
use App\Data\User\Company\CompanyVehicleFiscalRowData;
use App\Data\User\Company\CompanyYearStatsData;
use App\Data\User\Company\PaginatedCompanyListData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Pivot\DriverCompany;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Orchestration des lectures du domaine Company vers les DTOs exposés.
 *
 * Pré-charge en bulk les véhicules concernés via le repository pour
 * éviter tout N+1 dans le calcul d'agrégats fiscaux par entreprise.
 *
 * **Refonte 04.F (ADR-0014)** : `daysUsed` et `annualTaxDue` dérivés
 * de `ContractsByPair` au lieu de `AnnualCumulByPair`.
 */
final class CompanyQueryService
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
    ) {}

    /**
     * Index Companies paginé server-side (cf. ADR-0020).
     *
     * Le repo gère pagination + filtre `isActive` + search SQL. Le
     * service calcule ensuite les aggregates fiscaux (`daysUsed`,
     * `annualTaxDue`) uniquement pour les entreprises de la page courante.
     *
     * Note perf : `loadContractsByPair($year)` charge tous les contrats
     * de l'année (borne O(contrats/an), pas O(companies)). Acceptable
     * tant que les contrats annuels restent < 10k. À matérialiser si la
     * volumétrie explose (cf. ADR-0020 D6).
     */
    public function listPaginated(CompanyIndexQueryData $query, int $year): PaginatedCompanyListData
    {
        $paginator = $this->companies->paginateForIndex($query);

        // Pré-charge bulk pour le calcul des aggregates de la page.
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $vehicleIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehicleIdList = array_keys($vehicleIds);
        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);

        $items = array_map(
            fn (Company $c): CompanyListItemData => $this->mapCompanyToListItem(
                company: $c,
                year: $year,
                contractsByPair: $contractsByPair,
                vehiclesById: $vehiclesById,
                unavailabilitiesByVehicleId: $unavailabilitiesByVehicleId,
            ),
            $paginator->items(),
        );

        return new PaginatedCompanyListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * @param  Collection<int, Vehicle>  $vehiclesById
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     */
    private function mapCompanyToListItem(
        Company $company,
        int $year,
        ContractsByPair $contractsByPair,
        Collection $vehiclesById,
        array $unavailabilitiesByVehicleId,
    ): CompanyListItemData {
        return new CompanyListItemData(
            id: $company->id,
            legalName: $company->legal_name,
            shortCode: $company->short_code,
            color: $company->color,
            siren: $company->siren,
            city: $company->city,
            isActive: $company->is_active,
            daysUsed: $contractsByPair->daysByCompany($company->id, $year),
            annualTaxDue: $this->aggregator->companyAnnualTax(
                $company->id,
                $vehiclesById,
                $contractsByPair,
                $unavailabilitiesByVehicleId,
                $year,
            ),
        );
    }

    /**
     * Liste pour les `<SelectInput>`.
     *
     * @return DataCollection<int, CompanyOptionData>
     */
    public function listForOptions(): DataCollection
    {
        $rows = $this->companies->findAllForOptions()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return CompanyOptionData::collect($rows, DataCollection::class);
    }

    /**
     * Détail complet d'une entreprise pour la page Show — alimente :
     *  - le hero d'identité (intemporel)
     *  - les KPIs lifetime cumulés (`lifetime`)
     *  - la section « Historique par année » (`history`)
     *  - les onglets restants (Drivers, etc.) qui consomment les
     *    champs identitaires existants
     *
     * `currentRealYear` est exposé pour permettre à l'historique de
     * marquer l'exercice en cours sans dépendre de `new Date()`
     * côté front (cf. ADR-0020 D4).
     */
    public function detail(int $companyId): ?CompanyDetailData
    {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            return null;
        }

        $today = Carbon::today();
        $currentRealYear = (int) $today->year;

        // Drivers de cette entreprise (toutes memberships, actives + sorties)
        $company->load(['drivers' => function ($query): void {
            $query->orderByPivot('joined_at');
        }]);

        $contractsCountByDriver = Contract::query()
            ->where('company_id', $companyId)
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, COUNT(*) as cnt')
            ->groupBy('driver_id')
            ->pluck('cnt', 'driver_id')
            ->all();

        $driverRows = $company->drivers->map(function ($driver) use ($contractsCountByDriver, $today): CompanyDriverRowData {
            /** @var DriverCompany $pivot */
            $pivot = $driver->getAttribute('pivot');
            $first = (string) ($driver->first_name ?? '');
            $last = (string) ($driver->last_name ?? '');
            $fullName = trim($first.' '.$last);
            $initials = mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));

            return new CompanyDriverRowData(
                driverId: $driver->id,
                pivotId: $pivot->id,
                fullName: $fullName !== '' ? $fullName : '-',
                initials: $initials !== '' ? $initials : '-',
                joinedAt: $pivot->joined_at->toDateString(),
                leftAt: $pivot->left_at?->toDateString(),
                isCurrentlyActive: $pivot->left_at === null || $pivot->left_at->greaterThanOrEqualTo($today),
                contractsCount: (int) ($contractsCountByDriver[$driver->id] ?? 0),
            );
        })->values()->all();

        $activeDriversCount = 0;
        foreach ($driverRows as $row) {
            if ($row->isCurrentlyActive) {
                $activeDriversCount++;
            }
        }

        // ADR-0020 D3 — calcul des stats temporelles (lifetime + history)
        $contractsCount = $this->contracts->countContractsForCompany($companyId);
        $availableYears = $this->contracts->findActiveYearsForCompany($companyId);

        // Toutes les années où l'entreprise a au moins un contrat,
        // utilisées pour pré-calculer history + activityByYear (sans
        // distinction encore présent/passé).
        $allYearStats = [];
        foreach ($availableYears as $year) {
            $allYearStats[$year] = $this->computeYearStats($companyId, $year);
        }

        $lifetime = new CompanyLifetimeStatsData(
            daysUsed: array_sum(array_map(static fn (CompanyYearStatsData $s): int => $s->daysUsed, $allYearStats)),
            contractsCount: $contractsCount,
            taxesGenerated: round(
                array_sum(array_map(static fn (CompanyYearStatsData $s): float => $s->annualTaxDue, $allYearStats)),
                2,
                PHP_ROUND_HALF_UP,
            ),
            // V1.2 — la facturation des loyers n'est pas livrée. Le champ
            // est exposé en placeholder null pour que l'UI le rende dès
            // maintenant (carte KPI, branchement réel quand le module
            // facturation arrive).
            rentTotal: null,
        );

        // **Doctrine temporelle (chantier η Phase 1)** : 3 lentilles distinctes.
        //
        // Présent — KPIs en haut de page, toujours sur l'année calendaire
        // courante. Si l'entreprise n'a pas de contrat sur cette année,
        // on retourne un CompanyYearStatsData neutre (zéros) — l'UI
        // affichera "0 j / 0 contrats / 0 €" sans crash.
        $kpiYear = $this->availableYears->currentYear();
        $kpiStats = $allYearStats[$kpiYear]
            ?? new CompanyYearStatsData(
                year: $kpiYear,
                daysUsed: 0,
                contractsCount: 0,
                lcdCount: 0,
                lldCount: 0,
                annualTaxDue: 0.0,
                rent: null,
            );

        // Distingue "données absentes" (KPIs à 0) de "calcul fiscal
        // impossible" (règles fiscales pas encore codées pour kpiYear).
        // Permet à l'UI d'afficher un message court explicite sur la
        // KPI Taxes uniquement (cf. doctrine HD6).
        $kpiFiscalAvailable = in_array(
            $kpiYear,
            $this->fiscalRules->registeredYears(),
            true,
        );

        // Évolution — section Historique : toutes années passées avec
        // contrats (exclut kpiYear qui est dans les KPIs ci-dessus).
        // Évite la duplication info entre KPIs et Historique.
        $history = array_values(array_filter(
            $allYearStats,
            static fn (CompanyYearStatsData $s): bool => $s->year < $kpiYear,
        ));

        return new CompanyDetailData(
            id: $company->id,
            legalName: $company->legal_name,
            shortCode: $company->short_code,
            color: $company->color,
            siren: $company->siren,
            siret: $company->siret,
            addressLine1: $company->address_line_1,
            addressLine2: $company->address_line_2,
            postalCode: $company->postal_code,
            city: $company->city,
            country: $company->country,
            contactName: $company->contact_name,
            contactEmail: $company->contact_email,
            contactPhone: $company->contact_phone,
            isActive: $company->is_active,
            isOig: $company->is_oig,
            isIndividualBusiness: $company->is_individual_business,
            contractsCount: $contractsCount,
            activeDriversCount: $activeDriversCount,
            totalDriversCount: count($driverRows),
            drivers: $driverRows,
            lifetime: $lifetime,
            kpiStats: $kpiStats,
            kpiYear: $kpiYear,
            kpiFiscalAvailable: $kpiFiscalAvailable,
            history: $history,
            activityByYear: array_map(
                fn (int $year): CompanyActivityYearData => $this->computeActivityForYear($companyId, $year),
                $availableYears,
            ),
            availableYears: $availableYears,
            currentRealYear: $currentRealYear,
            yearScope: YearScopeData::fromResolver($this->availableYears),
        );
    }

    /**
     * Calcule l'activité détaillée d'une entreprise pour un exercice :
     * heatmap mensuelle (12 entiers, jours-véhicules / mois) + top 3
     * véhicules (triés desc par jours utilisés).
     *
     * Cette méthode dépend des informations véhicule (licensePlate,
     * brand, model) — les charge via le repo en bulk pour éviter les
     * N+1 lors de l'itération. Si l'entreprise n'a aucun pair sur
     * l'année (cas `availableYears` partiellement vide), retourne un
     * `CompanyActivityYearData` à zéros (12 cases vides + top vide).
     */
    private function computeActivityForYear(int $companyId, int $year): CompanyActivityYearData
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);

        // Pré-passe : on accumule par véhicule (pour le top) et par mois
        // (pour la heatmap), à partir des couples de l'entreprise sur
        // l'année. Un jour-véhicule = 1 unité ; deux véhicules attribués
        // simultanément le même jour = 2 unités sur le compteur du mois.
        /** @var array<int, int> $daysPerVehicle */
        $daysPerVehicle = [];
        $daysByMonth = array_fill(0, 12, 0);

        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            foreach ($pairContracts as $contract) {
                foreach ($contract->expandToDaysInYear($year) as $iso) {
                    $monthIndex = (int) substr($iso, 5, 2) - 1; // YYYY-MM-DD → 0..11
                    $daysByMonth[$monthIndex]++;
                    $daysPerVehicle[$vehicleId] = ($daysPerVehicle[$vehicleId] ?? 0) + 1;
                }
            }
        }

        if ($daysPerVehicle === []) {
            return new CompanyActivityYearData(
                year: $year,
                daysByMonth: $daysByMonth,
                topVehicles: [],
            );
        }

        // Top 3 véhicules — tri desc, limite 3.
        arsort($daysPerVehicle);
        $topVehicleIds = array_slice(array_keys($daysPerVehicle), 0, 3, preserve_keys: true);

        // Lookup bulk pour récupérer license_plate + brand + model des
        // véhicules du top (au plus 3 — coût négligeable).
        $vehiclesById = $this->vehicles->findByIdsIndexed($topVehicleIds);

        $totalVehicleDays = (int) array_sum($daysPerVehicle);

        $topVehicles = [];
        foreach ($topVehicleIds as $vehicleId) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $days = $daysPerVehicle[$vehicleId];
            $topVehicles[] = new CompanyTopVehicleData(
                vehicleId: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $days,
                percentage: $totalVehicleDays > 0
                    ? round($days / $totalVehicleDays * 100, 1)
                    : 0.0,
            );
        }

        return new CompanyActivityYearData(
            year: $year,
            daysByMonth: $daysByMonth,
            topVehicles: $topVehicles,
        );
    }

    /**
     * Calcule les KPIs annuels d'une entreprise pour une année donnée.
     * Charge les contrats de l'année (toutes flottes via aggregator) puis
     * filtre sur le couple `(vehicleId, $companyId)` côté `ContractsByPair`.
     */
    private function computeYearStats(int $companyId, int $year): CompanyYearStatsData
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);

        $vehicleIds = [];
        $lcdCount = 0;
        $lldCount = 0;
        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicleIds[] = $vehicleId;
            foreach ($pairContracts as $contract) {
                if ($contract->contract_type === ContractType::Lcd) {
                    $lcdCount++;
                } else {
                    $lldCount++;
                }
            }
        }

        $daysUsed = $contractsByPair->daysByCompany($companyId, $year);

        $annualTaxDue = 0.0;
        if ($vehicleIds !== []) {
            try {
                $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
                $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);
                $annualTaxDue = $this->aggregator->companyAnnualTax(
                    $companyId,
                    $vehiclesById,
                    $contractsByPair,
                    $unavailabilitiesByVehicleId,
                    $year,
                );
            } catch (FiscalCalculationException) {
                // L'année n'est pas configurée dans le calculateur
                // (cf. `config/floty.fiscal.available_years`). On laisse
                // `annualTaxDue: 0.0` plutôt que faire crasher la page —
                // l'utilisateur voit quand même les jours et le compte
                // de contrats pour cet exercice. Cas typique : contrats
                // antérieurs à la config fiscale, ou en avance sur
                // celle-ci.
                $annualTaxDue = 0.0;
            }
        }

        return new CompanyYearStatsData(
            year: $year,
            daysUsed: $daysUsed,
            contractsCount: $lcdCount + $lldCount,
            lcdCount: $lcdCount,
            lldCount: $lldCount,
            annualTaxDue: $annualTaxDue,
            rent: null,
        );
    }

    /**
     * Détail fiscal d'une entreprise pour l'année sélectionnée
     * (chantier N.2). 1 ligne par véhicule utilisé, totaux agrégés
     * (R-2024-003 : un seul arrondi par redevable).
     *
     * Si l'année n'est pas configurée dans le calculateur fiscal
     * (`config/floty.fiscal.available_years`), retourne des montants
     * à 0 plutôt que de faire crasher la page — l'utilisateur voit
     * quand même les jours et le compte de véhicules.
     */
    public function fiscalBreakdownForYear(int $companyId, int $year): CompanyFiscalYearData
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $currentRealYear = (int) Carbon::now()->year;
        $availableYears = $this->contracts->availableYearsRangeForCompany(
            $companyId,
            $currentRealYear,
        );

        $vehicleIds = [];
        $daysPerVehicle = [];
        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicleIds[] = $vehicleId;
            // Compteur de jours en pré-calcul, indépendant du pipeline
            // fiscal — utilisé pour la colonne `daysUsed` même si la
            // config fiscale de l'année est absente (cas FiscalCalculationException).
            $days = 0;
            foreach ($pairContracts as $contract) {
                $days += count($contract->expandToDaysInYear($year));
            }
            $daysPerVehicle[$vehicleId] = $days;
        }

        if ($vehicleIds === []) {
            return new CompanyFiscalYearData(
                year: $year,
                currentRealYear: $currentRealYear,
                rows: [],
                availableYears: $availableYears,
                totalDays: 0,
                totalTaxCo2: 0.0,
                totalTaxPollutants: 0.0,
                totalTaxAll: 0.0,
            );
        }

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        // Calcul du pipeline fiscal — encadré pour tolérer l'absence de
        // config fiscale sur l'année (cf. doc).
        $taxRowsByVehicleId = [];
        try {
            $rawRows = $this->aggregator->companyAnnualTaxBreakdownByVehicle(
                $companyId,
                $vehiclesById,
                $contractsByPair,
                $unavailabilitiesByVehicleId,
                $year,
            );
            foreach ($rawRows as $rawRow) {
                $taxRowsByVehicleId[$rawRow['vehicleId']] = $rawRow;
            }
        } catch (FiscalCalculationException) {
            $taxRowsByVehicleId = [];
        }

        $daysInYear = Carbon::createFromDate($year, 1, 1)->isLeapYear() ? 366 : 365;

        $rows = [];
        $totalDays = 0;
        $totalTaxCo2Raw = 0.0;
        $totalTaxPollutantsRaw = 0.0;

        foreach ($vehicleIds as $vehicleId) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }

            // `daysUsed` toujours pris du pré-calcul brut (jours d'attribution
            // sur l'année), pas du pipeline qui retourne `daysAssigned`
            // potentiellement réduit par R-2024-008 (indispos) ou R-2024-021
            // (LCD < 30j hors période). On veut afficher le brut consommé.
            $days = (int) ($daysPerVehicle[$vehicleId] ?? 0);
            $taxRow = $taxRowsByVehicleId[$vehicleId] ?? null;
            $taxCo2 = $taxRow !== null ? (float) $taxRow['taxCo2'] : 0.0;
            $taxPollutants = $taxRow !== null ? (float) $taxRow['taxPollutants'] : 0.0;
            $taxTotal = $taxRow !== null ? (float) $taxRow['taxTotal'] : 0.0;

            $proratoPercent = round($days / $daysInYear * 100, 1);

            $rows[] = new CompanyVehicleFiscalRowData(
                vehicleId: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $days,
                proratoPercent: $proratoPercent,
                taxCo2: $taxCo2,
                taxPollutants: $taxPollutants,
                taxTotal: $taxTotal,
            );

            $totalDays += $days;
            $totalTaxCo2Raw += $taxCo2;
            $totalTaxPollutantsRaw += $taxPollutants;
        }

        $totalTaxCo2 = round($totalTaxCo2Raw, 2, PHP_ROUND_HALF_UP);
        $totalTaxPollutants = round($totalTaxPollutantsRaw, 2, PHP_ROUND_HALF_UP);

        return new CompanyFiscalYearData(
            year: $year,
            currentRealYear: $currentRealYear,
            rows: $rows,
            availableYears: $availableYears,
            totalDays: $totalDays,
            totalTaxCo2: $totalTaxCo2,
            totalTaxPollutants: $totalTaxPollutants,
            totalTaxAll: round($totalTaxCo2 + $totalTaxPollutants, 2, PHP_ROUND_HALF_UP),
        );
    }

    /**
     * Couleurs disponibles pour un `<SelectInput>` (formulaire create).
     * Pas d'accès BDD : énumère un enum applicatif.
     *
     * @return DataCollection<int, CompanyColorOptionData>
     */
    public function colorOptions(): DataCollection
    {
        $rows = array_map(
            static fn (CompanyColor $c): CompanyColorOptionData => new CompanyColorOptionData(
                value: $c->value,
                label: $c->label(),
            ),
            CompanyColor::cases(),
        );

        return CompanyColorOptionData::collect($rows, DataCollection::class);
    }
}
