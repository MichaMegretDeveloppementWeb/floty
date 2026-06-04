<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Billing\ContractBillingBreakdownData;
use App\Data\User\Company\CompanyContractsStatsData;
use App\Data\User\Contract\ContractData;
use App\Data\User\Contract\ContractDocumentData;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Data\User\Contract\ContractListItemData;
use App\Data\User\Contract\ContractTaxBreakdownData;
use App\Data\User\Contract\ContractTaxYearBreakdownData;
use App\Data\User\Contract\PaginatedContractListData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Contract;
use App\Models\VehicleEvent;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Fiscal\Declaration\DeclarationAggregatorFactory;
use App\Services\Fiscal\FleetFiscalAggregator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Query service for the Contract domain. Composes the collections
 * returned by the repository into `DataCollection<DTO>` and exposes
 * aggregation helpers consumed by the fiscal engine and downstream
 * services. ADR-0013 compliant · no SQL here, only transformations.
 */
final readonly class ContractQueryService
{
    public function __construct(
        private ContractReadRepositoryInterface $repository,
        private VehicleEventReadRepositoryInterface $vehicleEventRepository,
        private ContractDocumentReadRepositoryInterface $documentRepository,
        private FleetFiscalAggregator $aggregator,
        private BillingBreakdownService $billingBreakdown,
        private RentalPriceCalculator $rentalPrice,
        private DeclarationAggregatorFactory $aggregatorFactory,
    ) {}

    /**
     * Single-contract billing breakdown, delegated to
     * {@see BillingBreakdownService::byContract()}. Returns `null` if
     * the contract does not exist.
     */
    public function findContractBillingBreakdown(int $id): ?ContractBillingBreakdownData
    {
        $contract = $this->repository->findById($id);

        if ($contract === null) {
            return null;
        }

        return $this->billingBreakdown->byContract($contract);
    }

    /**
     * Lists PDF documents attached to a contract.
     *
     * @return list<ContractDocumentData>
     */
    public function listDocumentsForContract(int $contractId): array
    {
        return $this->documentRepository
            ->listForContract($contractId)
            ->map(static fn ($d): ContractDocumentData => ContractDocumentData::fromModel($d))
            ->values()
            ->all();
    }

    public function findContractData(int $id): ?ContractData
    {
        $contract = $this->repository->findByIdWithRelations($id);

        if ($contract === null) {
            return null;
        }

        return ContractData::fromModel($contract);
    }

    /**
     * Fiscal façade for the contract detail page. Loads the contract
     * (with `vehicle.fiscalCharacteristics` eager-loaded via
     * `findByIdWithRelations`) and the vehicle unavailabilities, then
     * delegates to {@see FleetFiscalAggregator::contractTaxBreakdown()}.
     *
     * LCD hypothetical enrichment · for LCD contracts, runs a second
     * pipeline pass with an opt-out on R-YYYY-021 for the current
     * contract (cluster requalification mechanism, see
     * {@see DeclarationAggregatorFactory}) to compute what the contract
     * would cost if requalified as LLD · real pipeline (multi-VFC,
     * multi-rule) instead of a linear approximation. The values feed
     * the `hypothetical*` fields of the Year DTO. Paid only for LCD
     * contracts and only on Show (the Index does not call this method).
     */
    public function findContractTaxBreakdown(int $id): ?ContractTaxBreakdownData
    {
        $contract = $this->repository->findByIdWithRelations($id);

        if ($contract === null) {
            return null;
        }

        $vehicleEvents = $this->vehicleEventRepository
            ->findForVehicle($contract->vehicle_id)
            ->all();

        // A contract spanning a year without coded fiscal rules cannot be
        // tariffed (the pipeline throws `yearNotSupported`). Degrade to
        // `null` rather than letting the exception bubble up and make the
        // whole Show page inaccessible · billing, dates and documents
        // still render. The frontend distinguishes this from a missing VFC.
        try {
            $nominal = $this->aggregator->contractTaxBreakdown($contract, $vehicleEvents);
        } catch (FiscalCalculationException) {
            return null;
        }

        if ($contract->contract_type !== ContractType::Lcd) {
            return $nominal;
        }

        // Reached only when `$nominal` succeeded · every spanned year is
        // therefore supported, so the LCD opt-out factory never hits its
        // unsupported-year branch.
        return $this->enrichWithLcdHypothetical($contract, $vehicleEvents, $nominal);
    }

    /**
     * For an LCD contract, rebuilds the nominal breakdown with the
     * three `hypothetical*` fields populated through a real pipeline
     * pass with an opt-out (one per traversed year · decorators are
     * year-scoped).
     *
     * Always populated for LCDs, even when equal to the nominal
     * (vehicle outside the fiscal perimeter stays at 0 € even
     * requalified). The UI then renders "0 € si requalifié en LLD".
     *
     * @param  list<VehicleEvent>  $vehicleEvents
     */
    private function enrichWithLcdHypothetical(
        Contract $contract,
        array $vehicleEvents,
        ContractTaxBreakdownData $nominal,
    ): ContractTaxBreakdownData {
        $enrichedYears = [];

        foreach ($nominal->years as $year) {
            $optOutAggregator = $this->aggregatorFactory->buildFor($year->year, [$contract->id]);
            $optOutBreakdown = $optOutAggregator->contractTaxBreakdown($contract, $vehicleEvents);

            $optOutYear = null;
            foreach ($optOutBreakdown->years as $oy) {
                if ($oy->year === $year->year) {
                    $optOutYear = $oy;
                    break;
                }
            }

            if ($optOutYear === null) {
                $enrichedYears[] = $year;

                continue;
            }

            $enrichedYears[] = new ContractTaxYearBreakdownData(
                year: $year->year,
                daysInContractInYear: $year->daysInContractInYear,
                daysAssigned: $year->daysAssigned,
                daysInYear: $year->daysInYear,
                co2Method: $year->co2Method,
                pollutantCategory: $year->pollutantCategory,
                co2FullYearTariff: $year->co2FullYearTariff,
                pollutantsFullYearTariff: $year->pollutantsFullYearTariff,
                co2Due: $year->co2Due,
                pollutantsDue: $year->pollutantsDue,
                totalDue: $year->totalDue,
                appliedExemptions: $year->appliedExemptions,
                appliedRuleCodes: $year->appliedRuleCodes,
                appliedRules: $year->appliedRules,
                segments: $year->segments,
                hypotheticalCo2DueIfNoLcd: $optOutYear->co2Due,
                hypotheticalPollutantsDueIfNoLcd: $optOutYear->pollutantsDue,
                hypotheticalTotalDueIfNoLcd: $optOutYear->totalDue,
            );
        }

        return new ContractTaxBreakdownData(
            years: $enrichedYears,
            totalDue: $nominal->totalDue,
        );
    }

    /**
     * Server-side paginated Contracts Index (ADR-0020). The repository
     * handles pagination + filters + search + sort in SQL; the service
     * maps models to DTOs and enriches with costs.
     *
     * Used by pages that need costs in the initial payload (e.g. the
     * Contracts tab on the Company fiche). For the standalone Index,
     * prefer {@see listPaginatedSlim()} + {@see costsForContractIds()}
     * as a deferred prop.
     */
    public function listPaginated(ContractIndexQueryData $query): PaginatedContractListData
    {
        $paginator = $this->repository->paginateForIndex($query);
        $contracts = $paginator->items();

        // Single batch for the rentals of the page · avoids the N+1
        // (`forContract` × 25 items × M monthly pricing lookups).
        $rentalByContractId = $this->rentalPrice->forContracts($contracts);

        // Batch unavailabilities by distinct vehicle · 1 SQL instead
        // of N (feeds the real fiscal pipeline inside enrichContractDto).
        $vehicleIds = array_values(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $contracts,
        )));
        $vehicleEventsByVehicleId = $this->vehicleEventRepository
            ->findForVehicleIds($vehicleIds);

        // Prewarm VFC segments for every traversed year · avoids the
        // N+1 VFC query inside the contractTaxBreakdown loop.
        $this->prewarmVfcSegmentsForContracts($contracts);

        $items = array_map(
            fn (Contract $c): ContractListItemData => $this->enrichContractDto(
                $c,
                $rentalByContractId[$c->id] ?? null,
                $vehicleEventsByVehicleId[$c->vehicle_id] ?? [],
            ),
            $contracts,
        );

        return new PaginatedContractListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Slim variant of {@see listPaginated()} · does not compute costs
     * (`totalTax`, `rentalPrice` stay `null`). The initial payload is
     * served without paying the fiscal pipeline; the costs arrive in a
     * second `Inertia::defer` round-trip from `ContractController::index`
     * via {@see costsForContractIds()}. Saves ~210 ms cold on 25
     * contracts / 21 distinct vehicles.
     */
    public function listPaginatedSlim(ContractIndexQueryData $query): PaginatedContractListData
    {
        $paginator = $this->repository->paginateForIndex($query);
        $contracts = $paginator->items();

        $items = array_map(
            static fn (Contract $c): ContractListItemData => ContractListItemData::fromModel($c),
            $contracts,
        );

        return new PaginatedContractListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Per-contract costs map (`totalTax`, `rentalPrice`) for a batch
     * of contract ids. Used via `Inertia::defer` to fill the two cost
     * columns after the initial render.
     *
     * Fiscal computation delegates to
     * {@see FleetFiscalAggregator::contractTaxBreakdown()} · real
     * pipeline per contract, accounts for exemptions (R-2024-021 LCD =
     * 0 €), multi-VFC overlaps, intra-year scale changes · strictly
     * equivalent to the value displayed on the contract Show page.
     * Do NOT use a `fullYearTax × days/365` approximation · wrong as
     * soon as an exemption or scission applies.
     *
     * @param  list<int>  $contractIds
     * @return array<int, array{totalTax: float, rentalPrice: float|null}>
     */
    public function costsForContractIds(array $contractIds): array
    {
        if ($contractIds === []) {
            return [];
        }

        $contracts = $this->repository
            ->findByIdsWithRelations($contractIds)
            ->all();

        $vehicleIds = array_values(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $contracts,
        )));
        $vehicleEventsByVehicleId = $this->vehicleEventRepository
            ->findForVehicleIds($vehicleIds);

        $rentalByContractId = $this->rentalPrice->forContracts($contracts);

        $this->prewarmVfcSegmentsForContracts($contracts);

        $result = [];
        foreach ($contracts as $contract) {
            $totalTax = 0.0;
            try {
                $breakdown = $this->aggregator->contractTaxBreakdown(
                    $contract,
                    $vehicleEventsByVehicleId[$contract->vehicle_id] ?? [],
                );
                $totalTax = $breakdown->totalDue;
            } catch (\Throwable) {
                // Year outside the fiscal registry · totalTax = 0.
            }

            $rentalCents = $rentalByContractId[$contract->id] ?? null;

            $result[$contract->id] = [
                'totalTax' => $totalTax,
                'rentalPrice' => $rentalCents === null ? null : $rentalCents / 100,
            ];
        }

        return $result;
    }

    /**
     * Enriches the base DTO with live `totalTax` and `rentalPrice`.
     * The total tax goes through the real fiscal pipeline
     * ({@see FleetFiscalAggregator::contractTaxBreakdown()}) so it
     * matches the value shown on the contract Show page (LCD
     * exemptions, multi-VFC overlaps, …).
     *
     * `$preComputedRentalCents` · used by paginated indexes that have
     * batched the rentals upstream; falls back to a single lookup
     * when null (used on the isolated contract fiche).
     *
     * `$preComputedVehicleUnavailabilities` · used by paginated
     * indexes that batched unavailabilities (one query for every
     * distinct vehicle); falls back to a per-vehicle lookup when null.
     *
     * @param  list<VehicleEvent>|null  $preComputedVehicleUnavailabilities
     */
    private function enrichContractDto(
        Contract $contract,
        ?int $preComputedRentalCents = null,
        ?array $preComputedVehicleUnavailabilities = null,
    ): ContractListItemData {
        $base = ContractListItemData::fromModel($contract);

        $vehicleEvents = $preComputedVehicleUnavailabilities
            ?? $this->vehicleEventRepository->findForVehicle($contract->vehicle_id)->all();

        $totalTax = 0.0;
        try {
            $breakdown = $this->aggregator->contractTaxBreakdown($contract, $vehicleEvents);
            $totalTax = $breakdown->totalDue;
        } catch (\Throwable) {
            // Year outside the fiscal registry · totalTax left at 0.
        }

        $rentalCents = $preComputedRentalCents ?? $this->rentalPrice->forContract($contract->id);
        $rentalPrice = $rentalCents === null ? null : $rentalCents / 100;

        return new ContractListItemData(
            id: $base->id,
            vehicleId: $base->vehicleId,
            vehicleLicensePlate: $base->vehicleLicensePlate,
            vehicleIsExited: $base->vehicleIsExited,
            companyId: $base->companyId,
            companyShortCode: $base->companyShortCode,
            companyLegalName: $base->companyLegalName,
            companyColor: $base->companyColor,
            drivers: $base->drivers,
            startDate: $base->startDate,
            endDate: $base->endDate,
            durationDays: $base->durationDays,
            contractType: $base->contractType,
            contractReference: $base->contractReference,
            totalTax: $totalTax,
            rentalPrice: $rentalPrice,
        );
    }

    /**
     * Contextual stats displayed below the Contracts tab title on the
     * Company Show fiche. Days are intersected with the filtered
     * window (see repository PHPDoc).
     */
    public function statsForCompany(
        int $companyId,
        ?string $periodStart,
        ?string $periodEnd,
    ): CompanyContractsStatsData {
        $row = $this->repository->statsForCompanyInPeriod(
            $companyId,
            $periodStart,
            $periodEnd,
        );

        return new CompanyContractsStatsData(
            totalDays: $row['totalDays'],
            lcdCount: $row['lcdCount'],
            lldCount: $row['lldCount'],
        );
    }

    /**
     * Continuous `[firstYear..currentRealYear]` range for the
     * year-filter pills. Returns an empty array when the company has
     * no contract (pills hidden, the empty state suffices).
     *
     * Different from `availableYears` (= years with ≥ 1 contract) · the
     * pill range is continuous to avoid visual gaps and to allow
     * filtering an empty year to confirm the absence of contracts.
     *
     * @return list<int>
     */
    public function availableYearsRangeForCompany(
        int $companyId,
        int $currentRealYear,
    ): array {
        $first = $this->repository->firstContractYearForCompany($companyId);
        if ($first === null) {
            return [];
        }

        return range($first, $currentRealYear);
    }

    /**
     * Variant of `listPaginated` that forces `companyId` to the given
     * value · used by the Contracts tab on the Company fiche. The URL
     * query param is not trusted; the fiche imposes its `companyId`.
     */
    public function listPaginatedForCompany(
        int $companyId,
        ContractIndexQueryData $query,
    ): PaginatedContractListData {
        $scoped = new ContractIndexQueryData(
            vehicleId: $query->vehicleId,
            companyId: $companyId,
            driverId: $query->driverId,
            type: $query->type,
            year: $query->year,
            periodStart: $query->periodStart,
            periodEnd: $query->periodEnd,
            page: $query->page,
            perPage: $query->perPage,
            search: $query->search,
            sortKey: $query->sortKey,
            sortDirection: $query->sortDirection,
        );

        return $this->listPaginated($scoped);
    }

    /**
     * Contracts of a using company, for the Company show page.
     *
     * @return DataCollection<int, ContractListItemData>
     */
    public function listForCompany(int $companyId): DataCollection
    {
        $contracts = $this->repository->listForCompany($companyId);
        $contractsAll = $contracts->all();
        $rentalByContractId = $this->rentalPrice->forContracts($contractsAll);

        $vehicleIds = array_values(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $contractsAll,
        )));
        $vehicleEventsByVehicleId = $this->vehicleEventRepository
            ->findForVehicleIds($vehicleIds);

        $this->prewarmVfcSegmentsForContracts($contractsAll);

        /** @var DataCollection<int, ContractListItemData> */
        return ContractListItemData::collect(
            $contracts->map(
                fn (Contract $c): ContractListItemData => $this->enrichContractDto(
                    $c,
                    $rentalByContractId[$c->id] ?? null,
                    $vehicleEventsByVehicleId[$c->vehicle_id] ?? [],
                ),
            ),
            DataCollection::class,
        );
    }

    /**
     * Fiscal engine pivot · every active contract crossing the year
     * grouped by `(vehicleId, companyId)`.
     */
    public function loadContractsByPair(int $year): ContractsByPair
    {
        $byPair = [];
        foreach ($this->repository->findActiveForYear($year) as $contract) {
            $key = $contract->vehicle_id.'|'.$contract->company_id;
            $byPair[$key] ??= [];
            $byPair[$key][] = $contract;
        }

        return new ContractsByPair($byPair);
    }

    /**
     * Range variant of {@see loadContractsByPair()} · loads every
     * active contract over a year range in a single SQL query, returns
     * a `year → ContractsByPair` map. A contract is dispatched into
     * every year it crosses (a 2023-2025 contract appears in pivots
     * for 2023, 2024 and 2025).
     *
     * Use case · multi-year pages (Company Show overview, Dashboard
     * history) iterating N years · avoids N independent
     * `loadContractsByPair($year)` calls.
     *
     * @return array<int, ContractsByPair> keyed by year
     */
    public function loadContractsByPairForYearRange(int $from, int $to): array
    {
        $contracts = $this->repository->findActiveForYearRange($from, $to);

        $byYear = [];
        for ($year = $from; $year <= $to; $year++) {
            $byYear[$year] = [];
        }

        foreach ($contracts as $contract) {
            $startYear = (int) $contract->start_date->year;
            $endYear = (int) $contract->end_date->year;

            $clampedFrom = max($startYear, $from);
            $clampedTo = min($endYear, $to);

            for ($year = $clampedFrom; $year <= $clampedTo; $year++) {
                $key = $contract->vehicle_id.'|'.$contract->company_id;
                $byYear[$year][$key] ??= [];
                $byYear[$year][$key][] = $contract;
            }
        }

        $result = [];
        foreach ($byYear as $year => $byPair) {
            $result[$year] = new ContractsByPair($byPair);
        }

        return $result;
    }

    /**
     * Single-vehicle variant · used by the Vehicle show page, which
     * has no use for the rest of the fleet. Avoids materialising the
     * full pivot just to filter 1/N out.
     */
    public function loadContractsByPairForVehicle(int $vehicleId, int $year): ContractsByPair
    {
        $byPair = [];
        foreach ($this->repository->findByVehicleAndYear($vehicleId, $year) as $contract) {
            $key = $contract->vehicle_id.'|'.$contract->company_id;
            $byPair[$key] ??= [];
            $byPair[$key][] = $contract;
        }

        return new ContractsByPair($byPair);
    }

    /**
     * Unavailabilities by vehicle, feeding R-2024-008 (the rule
     * filters those crossing the year and the taxable contracts in
     * {@see App\Fiscal\Year2024\Reduction\R2024_008_ReductiveUnavailability::evaluate()}).
     *
     * Delegates to a single `WHERE vehicle_id IN (?)` on the repository;
     * a vehicle with no unavailability is absent from the map (callers
     * default to `[]`).
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, list<VehicleEvent>>
     */
    public function loadVehicleEventsByVehicle(array $vehicleIds): array
    {
        return $this->vehicleEventRepository->findForVehicleIds($vehicleIds);
    }

    /**
     * Total count of contracts for a company (all years). Delegated to
     * the repository, exposed via the service to keep the
     * Service → Service → Repository chain (ADR-0013).
     */
    public function countContractsForCompany(int $companyId): int
    {
        return $this->repository->countForCompany($companyId);
    }

    /**
     * Sorted list of years in which the company has at least one
     * active contract.
     *
     * @return list<int>
     */
    public function findActiveYearsForCompany(int $companyId): array
    {
        return $this->repository->findActiveYearsForCompany($companyId);
    }

    /**
     * For the Contract Create/Edit form · `vehicleId → list<ISO date>`
     * map of days already busy with another active contract on the
     * vehicle, over a `[today − 2 years, today + 2 years]` window.
     *
     * The range picker on the frontend consumes this map to grey out
     * unselectable days and stop the user from falling into the MySQL
     * anti-overlap trigger.
     *
     * On Edit, `excludeContractId` removes the dates of the currently
     * edited contract · otherwise the user could not re-save without
     * first "moving" it.
     *
     * @return array<int, list<string>>
     */
    public function busyDatesByVehicleAroundToday(?int $excludeContractId = null): array
    {
        $today = CarbonImmutable::today();
        $from = $today->subYears(2)->startOfYear()->toDateString();
        $to = $today->addYears(2)->endOfYear()->toDateString();

        $contracts = $this->repository->findAllInWindow($from, $to);

        $byVehicle = [];
        foreach ($contracts as $contract) {
            if ($excludeContractId !== null && $contract->id === $excludeContractId) {
                continue;
            }
            $vehicleId = $contract->vehicle_id;
            if (! isset($byVehicle[$vehicleId])) {
                $byVehicle[$vehicleId] = [];
            }
            foreach ($this->expandContractToRange($contract, $from, $to) as $date) {
                $byVehicle[$vehicleId][$date] = true;
            }
        }

        $result = [];
        foreach ($byVehicle as $vehicleId => $datesMap) {
            $list = array_keys($datesMap);
            sort($list);
            $result[$vehicleId] = $list;
        }

        return $result;
    }

    /**
     * ISO dates busy for a vehicle over a period · feeds `busyDates`
     * on the Vehicle Show page (unavailability calendar).
     *
     * @return list<string>
     */
    public function findDatesForVehicleInRange(int $vehicleId, string $from, string $to): array
    {
        $contracts = $this->repository
            ->findWindowContractsForVehicle(
                $vehicleId,
                CarbonImmutable::parse($from),
                CarbonImmutable::parse($to),
            );

        $dates = [];
        foreach ($contracts as $contract) {
            foreach ($this->expandContractToRange($contract, $from, $to) as $date) {
                $dates[$date] = true;
            }
        }
        $list = array_keys($dates);
        sort($list);

        return $list;
    }

    /**
     * ISO dates of a `(vehicle, company)` pair within the year · used
     * by the taxes preview (potential daily increment of a new
     * assignment in the planning drawer).
     *
     * @return list<string>
     */
    public function findDatesForPair(int $vehicleId, int $companyId, int $year): array
    {
        $dates = [];
        foreach ($this->repository->findByVehicleAndYear($vehicleId, $year) as $contract) {
            if ($contract->company_id !== $companyId) {
                continue;
            }
            foreach ($contract->expandToDaysInYear($year) as $date) {
                $dates[$date] = true;
            }
        }
        $list = array_keys($dates);
        sort($list);

        return $list;
    }

    /**
     * Weekly breakdown `week → companyId → days` for the 52-week
     * timeline on the Vehicle Show page.
     *
     * @return array<int, array<int, int>>
     */
    public function loadVehicleWeeklyBreakdown(int $vehicleId, int $year): array
    {
        $contracts = $this->repository->findByVehicleAndYear($vehicleId, $year);

        // Per-week arithmetic via {@see Contract::daysByWeekInYear}.
        // The business rule forbids two contracts of the same vehicle
        // on the same day, so cross-contract summation is safe without
        // a `Set<date>` defensive structure.
        $byWeek = [];
        foreach ($contracts as $contract) {
            $companyId = $contract->company_id;
            foreach ($contract->daysByWeekInYear($year) as $week => $days) {
                $byWeek[$week] ??= [];
                $byWeek[$week][$companyId] = ($byWeek[$week][$companyId] ?? 0) + $days;
            }
        }
        ksort($byWeek);

        return $byWeek;
    }

    /**
     * Per-vehicle × ISO-week density for the planning heatmap.
     * Key = `"vehicleId|weekNumber"`; value = number of days the
     * vehicle is busy with at least one contract that week.
     *
     * @return array<string, int>
     */
    public function loadWeekDensity(int $year): array
    {
        $contracts = $this->repository->findActiveForYear($year);

        $density = [];
        foreach ($contracts as $contract) {
            $vehicleId = $contract->vehicle_id;
            foreach ($contract->daysByWeekInYear($year) as $week => $days) {
                $key = $vehicleId.'|'.$week;
                $density[$key] = ($density[$key] ?? 0) + $days;
            }
        }

        return $density;
    }

    /**
     * Company-scoped variant of {@see loadWeekDensity()} · powers the
     * per-company planning heatmap where the cell number reflects
     * only the days used by that company (the colour stays driven by
     * the global density).
     *
     * @return array<string, int>
     */
    public function loadWeekDensityForCompany(int $year, int $companyId): array
    {
        $contracts = $this->repository->findActiveForYear($year);

        $density = [];
        foreach ($contracts as $contract) {
            if ($contract->company_id !== $companyId) {
                continue;
            }

            $vehicleId = $contract->vehicle_id;
            foreach ($contract->daysByWeekInYear($year) as $week => $days) {
                $key = $vehicleId.'|'.$week;
                $density[$key] = ($density[$key] ?? 0) + $days;
            }
        }

        return $density;
    }

    /**
     * Contracts of the vehicle overlapping `[start, end]` · used by
     * the planning week drawer (with `company` eager-loaded).
     *
     * @return Collection<int, Contract>
     */
    public function findWindowContractsForVehicle(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        return $this->repository->findWindowContractsForVehicle($vehicleId, $start, $end);
    }

    /**
     * Expands a contract into ISO days (Y-m-d), bounded by the given
     * year. Delegates to {@see Contract::expandToDaysInYear()} (helper
     * reused by the fiscal rules). Kept for backwards compatibility
     * with consumers that went through the service.
     *
     * @return list<string>
     */
    public function expandToDays(Contract $contract, int $year): array
    {
        return $contract->expandToDaysInYear($year);
    }

    /**
     * Prewarms the aggregator's `$vfcSegmentsCache` for every
     * `(vehicleId, year)` pair touched by the contract batch. A single
     * `findEffectiveSegmentsForYearBatch` per year feeds the cache;
     * subsequent `contractTaxBreakdown()` calls hit the cache instead
     * of refetching VFCs one contract at a time. On 25 contracts / 21
     * distinct vehicles for one year · drops from ~25 VFC queries to
     * 1. Multi-year contracts are dispatched into every traversed year.
     *
     * @param  list<Contract>  $contracts
     */
    private function prewarmVfcSegmentsForContracts(array $contracts): void
    {
        $vehiclesByYear = [];
        foreach ($contracts as $contract) {
            $startYear = (int) $contract->start_date->year;
            $endYear = (int) $contract->end_date->year;
            for ($year = $startYear; $year <= $endYear; $year++) {
                $vehiclesByYear[$year][$contract->vehicle_id] = $contract->vehicle;
            }
        }

        foreach ($vehiclesByYear as $year => $vehiclesById) {
            $this->aggregator->prewarmVfcSegmentsForVehicles(
                array_values($vehiclesById),
                $year,
            );
        }
    }

    /**
     * Expands a contract into ISO days clipped to an arbitrary
     * `[from, to]` window (not necessarily a year).
     *
     * @return list<string>
     */
    private function expandContractToRange(Contract $contract, string $from, string $to): array
    {
        $rangeStart = CarbonImmutable::parse($from);
        $rangeEnd = CarbonImmutable::parse($to);

        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

        $cursorStart = $start->isAfter($rangeStart) ? $start : $rangeStart;
        $cursorEnd = $end->isBefore($rangeEnd) ? $end : $rangeEnd;

        if ($cursorStart->isAfter($cursorEnd)) {
            return [];
        }

        $days = [];
        $cursor = $cursorStart;
        while (! $cursor->isAfter($cursorEnd)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
