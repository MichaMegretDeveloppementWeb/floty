<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use Carbon\CarbonImmutable;

/**
 * Temporal segment of a fiscal year over which the set of applicable
 * rules is stable.
 *
 * Emitted by
 * {@see RuleEffectiveSegmenter::segmentsForYear()}.
 * Bounds inclusive, clipped to the queried year:
 *   - `start` >= year-01-01
 *   - `end`   <= year-12-31
 *
 * A segment always spans at least one day. The `rules` list is always
 * non-empty (segments without applicable rule are not materialised).
 * Rule order matches the registry order for the year (preserves the
 * pipeline execution order).
 *
 * Consumed by {@see FiscalSegmentedExecutor},
 * which combines this segment with VFC segments to run the pipeline on
 * the cartesian product VFC × rules.
 *
 * Temporal analogue of {@see VfcEffectiveSegment}.
 */
final readonly class RuleEffectiveSegment
{
    /**
     * @param  non-empty-list<FiscalRule>  $rules
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $rules,
    ) {}
}
