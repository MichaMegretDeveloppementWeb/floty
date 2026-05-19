<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardPendingDeclarationItemData;
use App\Data\User\Dashboard\DashboardPendingInvoiceItemData;
use App\Data\User\Dashboard\DashboardPendingTasksData;
use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use App\Services\Fiscal\Declaration\DeclarationLifecycleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates pending items (declarations + invoices) across the entire
 * multi-company fleet for the Dashboard.
 *
 * Why a dedicated service · `DashboardStatsService` stays focused on
 * KPI / heatmap / evolution. Cross-company pending aggregation deserves
 * its own service · testable in isolation, with a dedicated batch
 * implementation tuned for the dashboard hot path.
 *
 * Batch strategy · instead of iterating companies and calling the
 * `pendingForCompany()` resolvers (which themselves trigger ~6 SQL each
 * via `BillingBreakdownService::byCompanyForYear`), the aggregator
 * loads everything in 5 global SQL queries ·
 *
 *   1. Active companies.
 *   2. Non-deleted contracts (`company_id`, `vehicle_id`, `start_date`,
 *      `end_date`).
 *   3. Non-deleted fiscal declarations.
 *   4. Relevant `vehicle_yearly_pricings`.
 *   5. Existing invoices (`company_id`, `year`, `month`).
 *
 * Then derives lifecycle states and counts missing months in PHP. On a
 * 5-company fleet · ~38 SQL → 5 SQL.
 *
 * Dashboard-specific simplifications ·
 *   - Drafts without a predecessor always resolve to `DraftPending`
 *     ("Continuer la revue"). Distinguishing from `DraftReadyToGenerate`
 *     ("Générer") would require a per-`(company, year)` call to
 *     `RiskDetection::detectClusters()`; too costly for the dashboard
 *     top 5. The full CTA is shown on the company fiche.
 *   - `pendingClustersCount` is always 0 (not displayed on the card).
 */
final readonly class DashboardPendingTasksAggregator
{
    private const TOP_ITEMS = 5;

    public function __construct(
        private CompanyReadRepositoryInterface $companies,
    ) {}

    public function aggregate(): DashboardPendingTasksData
    {
        $companies = $this->companies->findAllForOptions();
        if ($companies->isEmpty()) {
            return DashboardPendingTasksData::noPending();
        }

        $companyIds = $companies->pluck('id')->map(static fn ($v): int => (int) $v)->all();

        $contracts = DB::table('contracts')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->select(['id', 'company_id', 'vehicle_id', 'start_date', 'end_date'])
            ->get();

        if ($contracts->isEmpty()) {
            return DashboardPendingTasksData::noPending();
        }

        $yearsByCompany = $this->buildYearsByCompany($contracts);
        $contractsByCompany = $contracts->groupBy('company_id');

        $declsByKey = $this->loadDeclarations($companyIds);
        $pricingByKey = $this->loadPricings($contracts->pluck('vehicle_id')->unique()->all());
        $invoiceByKey = $this->loadExistingInvoices($companyIds);

        $now = CarbonImmutable::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        $declItems = [];
        $invItems = [];

        foreach ($companies as $company) {
            $companyId = (int) $company->id;
            $companyYears = array_keys($yearsByCompany[$companyId] ?? []);
            sort($companyYears);

            $companyContracts = $contractsByCompany->get($companyId, collect());

            foreach ($companyYears as $year) {
                $declItem = $this->buildDeclarationItem(
                    $company,
                    $companyId,
                    $year,
                    $declsByKey,
                    $now,
                    $currentYear,
                );
                if ($declItem !== null) {
                    $declItems[] = $declItem;
                }

                $invItem = $this->buildInvoiceItem(
                    $company,
                    $companyId,
                    $year,
                    $companyContracts,
                    $pricingByKey,
                    $invoiceByKey,
                    $currentYear,
                    $currentMonth,
                );
                if ($invItem !== null) {
                    $invItems[] = $invItem;
                }
            }
        }

        usort($declItems, static function (
            DashboardPendingDeclarationItemData $a,
            DashboardPendingDeclarationItemData $b,
        ): int {
            if ($a->isOverdue !== $b->isOverdue) {
                return $a->isOverdue ? -1 : 1;
            }

            return [$a->fiscalYear, $a->companyShortCode] <=> [$b->fiscalYear, $b->companyShortCode];
        });

        usort($invItems, static fn (
            DashboardPendingInvoiceItemData $a,
            DashboardPendingInvoiceItemData $b,
        ): int => [$a->fiscalYear, $a->companyShortCode] <=> [$b->fiscalYear, $b->companyShortCode]);

        $invMonthlyTotal = array_sum(array_map(
            static fn (DashboardPendingInvoiceItemData $item): int => $item->missingInvoicesCount,
            $invItems,
        ));

        return new DashboardPendingTasksData(
            pendingDeclarationsCount: count($declItems),
            pendingDeclarations: array_slice($declItems, 0, self::TOP_ITEMS),
            pendingInvoicesMonthlyTotal: $invMonthlyTotal,
            pendingInvoices: array_slice($invItems, 0, self::TOP_ITEMS),
        );
    }

    /**
     * Builds the `[companyId => [year => true]]` map covering every
     * year touched by each company's contracts.
     *
     * @param  Collection<int, \stdClass>  $contracts
     * @return array<int, array<int, true>>
     */
    private function buildYearsByCompany($contracts): array
    {
        $map = [];
        foreach ($contracts as $c) {
            $startY = (int) substr((string) $c->start_date, 0, 4);
            $endY = (int) substr((string) $c->end_date, 0, 4);
            for ($y = $startY; $y <= $endY; $y++) {
                $map[(int) $c->company_id][$y] = true;
            }
        }

        return $map;
    }

    /**
     * Loads every non-deleted declaration indexed by
     * `"{companyId}|{fiscalYear}"` (newest first via descending id).
     *
     * @param  list<int>  $companyIds
     * @return array<string, list<object>>
     */
    private function loadDeclarations(array $companyIds): array
    {
        $rows = DB::table('fiscal_declarations')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->select([
                'id',
                'company_id',
                'fiscal_year',
                'status',
                'is_obsolete',
                'obsolete_at',
                'obsolete_reasons',
                'superseded_by_id',
            ])
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $key = $row->company_id.'|'.$row->fiscal_year;
            $indexed[$key][] = $row;
        }

        return $indexed;
    }

    /**
     * Loads every yearly pricing indexed by `"{vehicleId}|{year}"`.
     *
     * @param  list<int>  $vehicleIds
     * @return array<string, true>
     */
    private function loadPricings(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $rows = DB::table('vehicle_yearly_pricings')
            ->whereIn('vehicle_id', $vehicleIds)
            ->select(['vehicle_id', 'year'])
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->vehicle_id.'|'.$row->year] = true;
        }

        return $indexed;
    }

    /**
     * Loads existing invoices indexed by `"{companyId}|{year}|{month}"`.
     *
     * @param  list<int>  $companyIds
     * @return array<string, true>
     */
    private function loadExistingInvoices(array $companyIds): array
    {
        $rows = DB::table('invoices')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->select(['company_id', 'year', 'month'])
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->company_id.'|'.$row->year.'|'.$row->month] = true;
        }

        return $indexed;
    }

    /**
     * Builds a "pending declaration" item for a `(company, year)` pair,
     * or null when the year is not pending.
     *
     * @param  array<string, list<object>>  $declsByKey
     */
    private function buildDeclarationItem(
        object $company,
        int $companyId,
        int $year,
        array $declsByKey,
        CarbonImmutable $now,
        int $currentYear,
    ): ?DashboardPendingDeclarationItemData {
        $declList = $declsByKey[$companyId.'|'.$year] ?? [];

        [$head, $predecessor] = $this->findHeadAndPredecessor($declList);
        $state = $this->deriveState($head, $predecessor);

        if ($state === DeclarationLifecycleState::GeneratedActive) {
            return null;
        }
        if ($state === DeclarationLifecycleState::Untouched && $year >= $currentYear) {
            return null;
        }

        $deadline = sprintf('%04d-04-30', $year + 1);
        $isOverdue = $now->isAfter(CarbonImmutable::parse($deadline));

        [$obsoleteSinceDate, $obsoleteReasonsCount] = $this->resolveObsoleteContext($head, $predecessor, $state);

        return new DashboardPendingDeclarationItemData(
            companyId: $companyId,
            companyShortCode: (string) $company->short_code,
            companyLegalName: (string) $company->legal_name,
            fiscalYear: $year,
            deadline: $deadline,
            isOverdue: $isOverdue,
            state: $state,
            currentDeclarationId: $head !== null ? (int) $head->id : null,
            pendingClustersCount: 0,
            obsoleteSinceDate: $obsoleteSinceDate,
            obsoleteReasonsCount: $obsoleteReasonsCount,
        );
    }

    /**
     * Builds a "pending invoices" item for a `(company, year)` pair, or
     * null when no month is missing.
     *
     * @param  Collection<int, \stdClass>  $companyContracts
     * @param  array<string, true>  $pricingByKey
     * @param  array<string, true>  $invoiceByKey
     */
    private function buildInvoiceItem(
        object $company,
        int $companyId,
        int $year,
        $companyContracts,
        array $pricingByKey,
        array $invoiceByKey,
        int $currentYear,
        int $currentMonth,
    ): ?DashboardPendingInvoiceItemData {
        $maxMonth = $year < $currentYear ? 12 : $currentMonth - 1;
        if ($maxMonth < 1) {
            return null;
        }
        if ($year > $currentYear) {
            return null;
        }

        $missingCount = 0;
        for ($month = 1; $month <= $maxMonth; $month++) {
            $monthStart = CarbonImmutable::create($year, $month, 1);
            $monthStartStr = $monthStart->toDateString();
            $monthEndStr = $monthStart->endOfMonth()->toDateString();

            $vehicleIdsInMonth = [];
            foreach ($companyContracts as $c) {
                $cStart = substr((string) $c->start_date, 0, 10);
                $cEnd = substr((string) $c->end_date, 0, 10);

                if ($cStart <= $monthEndStr && $cEnd >= $monthStartStr) {
                    $vehicleIdsInMonth[(int) $c->vehicle_id] = true;
                }
            }
            if ($vehicleIdsInMonth === []) {
                continue;
            }

            $hasAllPricing = true;
            foreach (array_keys($vehicleIdsInMonth) as $vid) {
                if (! isset($pricingByKey[$vid.'|'.$year])) {
                    $hasAllPricing = false;
                    break;
                }
            }
            if (! $hasAllPricing) {
                continue;
            }

            if (isset($invoiceByKey[$companyId.'|'.$year.'|'.$month])) {
                continue;
            }

            $missingCount++;
        }

        if ($missingCount <= 0) {
            return null;
        }

        return new DashboardPendingInvoiceItemData(
            companyId: $companyId,
            companyShortCode: (string) $company->short_code,
            companyLegalName: (string) $company->legal_name,
            fiscalYear: $year,
            missingInvoicesCount: $missingCount,
        );
    }

    /**
     * Locates the chain head (`superseded_by_id IS NULL`) and its
     * optional predecessor (another declaration of the same year with
     * `superseded_by_id = head.id`).
     *
     * @param  list<object>  $declList
     * @return array{0: ?object, 1: ?object}
     */
    private function findHeadAndPredecessor(array $declList): array
    {
        $head = null;
        foreach ($declList as $d) {
            if ($d->superseded_by_id === null) {
                $head = $d;
                break;
            }
        }

        $predecessor = null;
        if ($head !== null) {
            foreach ($declList as $d) {
                if ($d->superseded_by_id !== null && (int) $d->superseded_by_id === (int) $head->id) {
                    $predecessor = $d;
                    break;
                }
            }
        }

        return [$head, $predecessor];
    }

    /**
     * Maps `(head, predecessor)` to a `DeclarationLifecycleState`,
     * aligned with {@see DeclarationLifecycleResolver::deriveState()}
     * except that drafts without predecessor always resolve to
     * `DraftPending` (cf. class PHPDoc).
     */
    private function deriveState(?object $head, ?object $predecessor): DeclarationLifecycleState
    {
        if ($head === null) {
            return DeclarationLifecycleState::Untouched;
        }

        $hasPredecessor = $predecessor !== null;
        $status = (string) $head->status;

        if ($status === 'draft') {
            return $hasPredecessor
                ? DeclarationLifecycleState::RegenerationInProgress
                : DeclarationLifecycleState::DraftPending;
        }

        if ($status === 'deferred') {
            return $hasPredecessor
                ? DeclarationLifecycleState::DeferredRegeneration
                : DeclarationLifecycleState::Deferred;
        }

        return (int) $head->is_obsolete === 1
            ? DeclarationLifecycleState::GeneratedObsoleteOrphan
            : DeclarationLifecycleState::GeneratedActive;
    }

    /**
     * Extracts `obsolete_at` and the reasons count from the carrier
     * declaration (head for S6, predecessor for S7).
     *
     * @return array{0: ?string, 1: int}
     */
    private function resolveObsoleteContext(
        ?object $head,
        ?object $predecessor,
        DeclarationLifecycleState $state,
    ): array {
        $source = match ($state) {
            DeclarationLifecycleState::GeneratedObsoleteOrphan => $head,
            DeclarationLifecycleState::RegenerationInProgress,
            DeclarationLifecycleState::DeferredRegeneration => $predecessor,
            default => null,
        };

        if ($source === null) {
            return [null, 0];
        }

        $rawDate = $source->obsolete_at !== null ? substr((string) $source->obsolete_at, 0, 10) : null;
        $reasons = $source->obsolete_reasons;
        if (is_string($reasons)) {
            $reasons = json_decode($reasons, true);
        }
        $count = is_array($reasons) ? count($reasons) : 0;

        return [$rawDate, $count];
    }
}
