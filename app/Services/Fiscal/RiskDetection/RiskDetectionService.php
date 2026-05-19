<?php

declare(strict_types=1);

namespace App\Services\Fiscal\RiskDetection;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\ClusterContractData;
use App\Data\User\FiscalDeclaration\ReviewClusterData;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\Contract;
use App\Models\FiscalRiskSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Detects fiscal risk clusters for a `(company, year)` pair (ADR-0015 § 4).
 *
 * Pure computation · no persistence, no mutation, no IO besides
 * reading contracts and risk settings. Output is consumed by the
 * declaration Actions and the review page.
 *
 * Scope · the using company × calendar year, all vehicles included.
 * Chains never cross companies. LCD authority is the fiscal rule
 * (`R-2024-021` · {@see LcdContractFilter}), never the persisted
 * `contract_type`. SQL ordering (`start_date ASC, id ASC`) combined
 * with fingerprint ordering by `id ASC` guarantees deterministic output
 * for a given input (required by ADR § 6.5 re-review reuse).
 */
final class RiskDetectionService
{
    /**
     * Per-request memoization keyed by `"{companyId}|{year}"`.
     *
     * Scope is strictly per-request (Laravel resolves the service per
     * HTTP request and it is garbage-collected at the end). Safe as
     * long as no consumer mutates contracts or `FiscalRiskSettings`
     * between two calls for the same pair within the same request.
     *
     * Hot path · `GenerateDeclarationAction::execute` and
     * `DeclarationController` (preview + show) both call
     * `DeclarationPreviewService::preview()` then
     * `DeclarationFiscalEngine::compute()`, each triggering
     * `detectClusters($companyId, $year)`. Without the cache the
     * chain + fingerprint work runs twice.
     *
     * {@see clearCache()} provides a manual escape hatch for future
     * actions that might mutate contracts and recompute in the same
     * request.
     *
     * @var array<string, list<ReviewClusterData>>
     */
    private array $clustersCache = [];

    public function __construct(
        private readonly ContractReadRepositoryInterface $contracts,
        private readonly FiscalRiskSettingsReadRepositoryInterface $settingsRepo,
        private readonly LcdContractFilter $lcdFilter,
        private readonly FingerprintService $fingerprint,
    ) {}

    /**
     * @return list<ReviewClusterData>
     */
    public function detectClusters(int $companyId, int $year): array
    {
        $key = $companyId.'|'.$year;

        return $this->clustersCache[$key] ??= $this->computeClusters($companyId, $year);
    }

    /**
     * Clears the per-request memoization cache. Use only when an Action
     * mutates contracts or risk settings and must recompute clusters
     * within the same HTTP request.
     */
    public function clearCache(): void
    {
        $this->clustersCache = [];
    }

    /**
     * @return list<ReviewClusterData>
     */
    private function computeClusters(int $companyId, int $year): array
    {
        $allContracts = $this->contracts->findForCompanyAndYear($companyId, $year);
        if ($allContracts->isEmpty()) {
            return [];
        }

        $settings = $this->settingsRepo->get();

        $chains = $this->buildChains($allContracts, $settings);

        $clusters = [];
        foreach ($chains as $chain) {
            $cluster = $this->qualifyChain($chain, $year, $settings);
            if ($cluster !== null) {
                $clusters[] = $cluster;
            }
        }

        return $clusters;
    }

    /**
     * Chain algorithm (ADR-0015 § 4) · a chain groups successive LCD
     * contracts separated by at most `max_interval` full days.
     * Interleaved LLD contracts are silently ignored · they bear no
     * business relationship to an LCD chain (an LCD chain captures
     * temporal continuity of short-term usage, independent of any LLD
     * contract on other vehicles).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return list<list<Contract>>
     */
    private function buildChains(Collection $contracts, FiscalRiskSettings $settings): array
    {
        /** @var list<list<Contract>> $chains */
        $chains = [];
        /** @var list<Contract> $current */
        $current = [];

        foreach ($contracts as $contract) {
            if (! $this->lcdFilter->isLcd($contract)) {
                continue;
            }

            if ($current === []) {
                $current = [$contract];

                continue;
            }

            $previous = $current[count($current) - 1];
            $interval = $this->intervalDays($previous, $contract);

            if ($interval <= $settings->max_interval) {
                $current[] = $contract;
            } else {
                $this->flushChain($chains, $current);
                $current = [$contract];
            }
        }

        $this->flushChain($chains, $current);

        return $chains;
    }

    /**
     * @param  list<list<Contract>>  $chains
     * @param  list<Contract>  $current
     */
    private function flushChain(array &$chains, array $current): void
    {
        if (count($current) >= 2) {
            $chains[] = $current;
        }
    }

    private function intervalDays(Contract $previous, Contract $next): int
    {
        return (int) $previous->end_date->copy()->diffInDays($next->start_date) - 1;
    }

    /**
     * Qualifies a chain as R-LCD-CHAIN, R-LCD-CHAIN-FORT or nothing.
     *
     * Threshold criterion · union of unique days covered by at least
     * one contract in the chain, with intervals merged and clipped to
     * the fiscal year. Exact handling for two symmetric traps ·
     *
     *   - Overlapping contracts (e.g. three 17-day contracts on the
     *     same 17 days) · the naive sum (51 d) overcounts; the union
     *     restores reality (17 d).
     *   - Sparse chains (e.g. 15 d → 50-day gap → 8 d) · the
     *     start-to-end span (73 d) overcounts; the union retains only
     *     the days of actual usage (23 d).
     *
     * `coverageStartDate` / `coverageEndDate` keep their meaning as
     * the outer bounds (first start, last end, clipped to the year)
     * for header display only · they do not feed the threshold.
     *
     * @param  list<Contract>  $chain
     */
    private function qualifyChain(array $chain, int $year, FiscalRiskSettings $settings): ?ReviewClusterData
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $intervals = $this->boundedIntervals($chain, $yearStart, $yearEnd);
        if ($intervals === []) {
            return null;
        }

        $coveragePeriodDays = $this->unionDays($intervals);

        $coverageStart = $intervals[0][0];
        $coverageEnd = $intervals[0][1];
        foreach ($intervals as [$start, $end]) {
            if ($start->lt($coverageStart)) {
                $coverageStart = $start;
            }
            if ($end->gt($coverageEnd)) {
                $coverageEnd = $end;
            }
        }

        $count = count($chain);
        $distinctVehiclesCount = count(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $chain,
        )));

        $code = match (true) {
            $coveragePeriodDays > $settings->threshold_high || $count >= $settings->count_high => RiskCode::ChainFort,
            $coveragePeriodDays > $settings->threshold_low => RiskCode::Chain,
            default => null,
        };

        if ($code === null) {
            return null;
        }

        return new ReviewClusterData(
            code: $code,
            level: $code->level(),
            fingerprint: $this->fingerprint->compute($chain),
            contracts: $this->buildContractDtos($chain, $year),
            contractsCount: $count,
            coveragePeriodDays: $coveragePeriodDays,
            coverageStartDate: $coverageStart->toDateString(),
            coverageEndDate: $coverageEnd->toDateString(),
            distinctVehiclesCount: $distinctVehiclesCount,
            decision: null,
            justification: null,
        );
    }

    /**
     * Returns the `[start, end]` interval list of every contract in
     * the chain, clipped to the fiscal year and dropping contracts
     * entirely outside the year.
     *
     * @param  list<Contract>  $chain
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function boundedIntervals(array $chain, CarbonImmutable $yearStart, CarbonImmutable $yearEnd): array
    {
        $intervals = [];
        foreach ($chain as $contract) {
            $start = $contract->start_date->lt($yearStart)
                ? $yearStart
                : CarbonImmutable::parse($contract->start_date);
            $end = $contract->end_date->gt($yearEnd)
                ? $yearEnd
                : CarbonImmutable::parse($contract->end_date);
            if ($end->lt($start)) {
                continue;
            }
            $intervals[] = [$start, $end];
        }

        return $intervals;
    }

    /**
     * Union of unique days covered by at least one interval. Sorts by
     * start, merges overlapping or contiguous intervals (an interval
     * starting the day after the previous end is merged · 1-10 ∪ 11-20
     * = 1-20), then sums inclusive durations.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private function unionDays(array $intervals): int
    {
        usort(
            $intervals,
            static fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp(),
        );

        /** @var list<array{0: CarbonImmutable, 1: CarbonImmutable}> $merged */
        $merged = [$intervals[0]];
        $count = count($intervals);
        for ($i = 1; $i < $count; $i++) {
            [$start, $end] = $intervals[$i];
            $lastIndex = count($merged) - 1;
            [$lastStart, $lastEnd] = $merged[$lastIndex];

            if ($start->lessThanOrEqualTo($lastEnd->addDay())) {
                $merged[$lastIndex] = [
                    $lastStart,
                    $end->greaterThan($lastEnd) ? $end : $lastEnd,
                ];
            } else {
                $merged[] = [$start, $end];
            }
        }

        $total = 0;
        foreach ($merged as [$start, $end]) {
            $total += (int) $start->diffInDays($end) + 1;
        }

        return $total;
    }

    /**
     * @param  list<Contract>  $chain
     * @return list<ClusterContractData>
     */
    private function buildContractDtos(array $chain, int $year): array
    {
        $dtos = [];
        $previous = null;
        foreach ($chain as $contract) {
            $dtos[] = ClusterContractData::fromContract($contract, $year, $previous);
            $previous = $contract;
        }

        return $dtos;
    }
}
