<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\ValueObjects\RuleEffectiveSegment;
use Carbon\CarbonImmutable;

/**
 * Slices a fiscal year into sub-periods over which the set of
 * applicable rules is stable.
 *
 * Each rule declares its applicability window. Most rules cover the
 * whole year, but eventually some may appear or disappear mid-year
 * (e.g. CIBS bracket change on July 1st, partial 2025 rule extended to
 * 2026-03-03). Consumed by {@see FiscalSegmentedExecutor} so the
 * pipeline can tariff each sub-period with its own rule set.
 *
 * Algorithm: sweep-line over unique rule bounds clipped to the year.
 * Complexity O(N log N) on N rules. Typical 2024 case (16 annual
 * rules): a single segment covering `[2024-01-01, 2024-12-31]`.
 *
 * Per-process memory cache: the segmenter is registered as a singleton,
 * segments are memoised by year. Same-year queries within one HTTP
 * request cost nothing. To invalidate in tests:
 * `$this->app->forgetInstance(RuleEffectiveSegmenter::class)`.
 *
 * Bound semantics: inclusive, day granularity. A segment always covers
 * at least one day. The rules list of a segment is always non-empty.
 *
 * Temporal analogue of the VFC segment repository
 * (`VehicleFiscalCharacteristicsReadRepository::findEffectiveSegmentsForYear()`).
 */
final class RuleEffectiveSegmenter
{
    /**
     * @var array<int, list<RuleEffectiveSegment>>
     */
    private array $cache = [];

    public function __construct(private readonly FiscalRuleRegistry $registry) {}

    /**
     * @return list<RuleEffectiveSegment>
     */
    public function segmentsForYear(int $year): array
    {
        if (isset($this->cache[$year])) {
            return $this->cache[$year];
        }

        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->startOfDay();

        $rules = $this->registry->rulesForYear($year);

        /** @var list<array{rule: FiscalRule, start: CarbonImmutable, end: CarbonImmutable}> $clipped */
        $clipped = [];
        foreach ($rules as $rule) {
            $start = $rule->applicabilityStart()->startOfDay();
            if ($start->lessThan($yearStart)) {
                $start = $yearStart;
            }

            $endRaw = $rule->applicabilityEnd();
            $end = $endRaw === null ? $yearEnd : $endRaw->startOfDay();
            if ($end->greaterThan($yearEnd)) {
                $end = $yearEnd;
            }

            if ($start->greaterThan($end)) {
                continue;
            }

            $clipped[] = ['rule' => $rule, 'start' => $start, 'end' => $end];
        }

        if ($clipped === []) {
            return $this->cache[$year] = [];
        }

        // Unique boundaries (each rule's start + end+1day). The +1day
        // lets the sweep produce adjacent segments without overlap or
        // gap. Indexing by the ISO `YYYY-MM-DD` string lets `ksort`
        // produce boundaries in chronological order without expensive
        // Carbon comparisons.
        $boundaryKeys = [];
        foreach ($clipped as $c) {
            $boundaryKeys[$c['start']->toDateString()] = $c['start'];
            $afterEnd = $c['end']->addDay();
            $boundaryKeys[$afterEnd->toDateString()] = $afterEnd;
        }
        ksort($boundaryKeys);
        $boundaries = array_values($boundaryKeys);

        $segments = [];
        $count = count($boundaries);
        for ($i = 0; $i < $count - 1; $i++) {
            $segStart = $boundaries[$i];
            $segEnd = $boundaries[$i + 1]->subDay();

            $activeRules = [];
            foreach ($clipped as $c) {
                if (! $c['start']->greaterThan($segStart) && ! $c['end']->lessThan($segEnd)) {
                    $activeRules[] = $c['rule'];
                }
            }

            if ($activeRules === []) {
                continue;
            }

            $segments[] = new RuleEffectiveSegment(
                start: $segStart,
                end: $segEnd,
                rules: $activeRules,
            );
        }

        return $this->cache[$year] = $segments;
    }

    /**
     * Invalidates the per-process memory cache.
     *
     * Used when the `FiscalRuleRegistry` is mutated at runtime
     * (typically tests calling `$registry->register($stubYear, [...])`).
     * In production the registry is frozen at boot, so `clearCache()`
     * is unnecessary.
     *
     * When `$year` is provided only that slot is purged; otherwise the
     * whole cache is emptied.
     */
    public function clearCache(?int $year = null): void
    {
        if ($year === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$year]);
    }
}
