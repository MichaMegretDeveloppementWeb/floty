<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Company\CompanyColorOptionData;
use App\Data\User\Company\CompanyDetailData;
use App\Data\User\Company\CompanyDriverRowData;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Data\User\Company\CompanyLifetimeStatsData;
use App\Data\User\Company\CompanyListItemData;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Company\CompanyYearStatsData;
use App\Data\User\Company\PaginatedCompanyListData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Pivot\DriverCompany;
use App\Services\Contract\ContractQueryService;
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
    ) {}

    /**
     * Liste des entreprises pour la page « Entreprises utilisatrices »
     * avec jours utilisés + taxe annuelle agrégée par entreprise.
     *
     * @return DataCollection<int, CompanyListItemData>
     *
     * @deprecated Conservé temporairement — sera retiré en L6 du
     *             chantier ADR-0020. Utiliser {@see listPaginated()}.
     */
    public function listForFleetView(int $year): DataCollection
    {
        $rows = $this->companies->findAllOrderedByName()
            ->map(fn (Company $c): CompanyListItemData => $this->mapCompanyToListItem(
                company: $c,
                year: $year,
                contractsByPair: $this->contracts->loadContractsByPair($year),
                vehiclesById: null,
                unavailabilitiesByVehicleId: null,
            ))
            ->values()
            ->all();

        return CompanyListItemData::collect($rows, DataCollection::class);
    }

    /**
     * Index Companies paginé server-side (cf. ADR-0020).
     *
     * Le repo gère pagination + filtre `isActive` + search SQL. Le
     * service calcule ensuite les aggregates fiscaux (`daysUsed`,
     * `annualTaxDue`) **uniquement pour les entreprises de la page
     * courante** — pas pour tout le dataset, contrairement à
     * `listForFleetView()`.
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

    private function mapCompanyToListItem(
        Company $company,
        int $year,
        ContractsByPair $contractsByPair,
        ?Collection $vehiclesById,
        ?array $unavailabilitiesByVehicleId,
    ): CompanyListItemData {
        // Si le pré-load n'a pas été passé (cas listForFleetView deprecated),
        // on charge à la volée (perf moindre mais correct jusqu'à L6).
        // Note : vehicleCompanyPairs() retourne un Generator → on itère
        // pour collecter les vehicleIds uniques.
        if ($vehiclesById === null) {
            $vehicleIds = [];
            foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
                $vehicleIds[$pair['vehicleId']] = true;
            }
            $vehiclesById = $this->vehicles->findByIdsIndexed(array_keys($vehicleIds));
        }
        $unavailabilitiesByVehicleId ??= $this->contracts->loadUnavailabilitiesByVehicle(
            $vehiclesById->keys()->all(),
        );

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
     *  - la section « Depuis le début » (`lifetime`)
     *  - la section « Aperçu par année » (`byYear`, piloté par
     *    `$selectedYear`)
     *  - la section « Historique par année » (`history`)
     *  - les onglets restants (Drivers, etc.) qui consomment les
     *    champs identitaires existants
     *
     * `$selectedYear` est l'année active du sélecteur **local** de la
     * fiche (cf. ADR-0020 D3 — pas de sélecteur global). Si `null`
     * passé, fallback à l'année calendaire réelle.
     */
    public function detail(int $companyId, ?int $selectedYear = null): ?CompanyDetailData
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
            $pivot = $driver->pivot;
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

        // ADR-0020 D3 — calcul des stats temporelles (lifetime + history + byYear)
        $contractsCount = $this->contracts->countContractsForCompany($companyId);
        $availableYears = $this->contracts->findActiveYearsForCompany($companyId);

        $history = [];
        foreach ($availableYears as $year) {
            $history[] = $this->computeYearStats($companyId, $year);
        }

        $effectiveYear = $selectedYear ?? $currentRealYear;
        $byYear = $this->findStatsForYear($history, $effectiveYear)
            ?? $this->emptyYearStats($effectiveYear);

        $lifetime = new CompanyLifetimeStatsData(
            daysUsed: array_sum(array_map(static fn (CompanyYearStatsData $s): int => $s->daysUsed, $history)),
            contractsCount: $contractsCount,
            taxesGenerated: round(
                array_sum(array_map(static fn (CompanyYearStatsData $s): float => $s->annualTaxDue, $history)),
                2,
                PHP_ROUND_HALF_UP,
            ),
        );

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
            byYear: $byYear,
            history: $history,
            availableYears: $availableYears,
            currentRealYear: $currentRealYear,
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
     * Cherche dans `$history` l'entrée correspondant à `$year`. Retourne
     * `null` si l'entreprise n'avait aucun contrat sur cette année.
     *
     * @param  list<CompanyYearStatsData>  $history
     */
    private function findStatsForYear(array $history, int $year): ?CompanyYearStatsData
    {
        foreach ($history as $stat) {
            if ($stat->year === $year) {
                return $stat;
            }
        }

        return null;
    }

    /**
     * Stats vides pour une année où l'entreprise n'a aucun contrat —
     * permet à `byYear` de toujours être renseigné (l'utilisateur a
     * sélectionné une année future ou hors plage d'activité).
     */
    private function emptyYearStats(int $year): CompanyYearStatsData
    {
        return new CompanyYearStatsData(
            year: $year,
            daysUsed: 0,
            contractsCount: 0,
            lcdCount: 0,
            lldCount: 0,
            annualTaxDue: 0.0,
            rent: null,
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
