<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * Transversal rule: daily prorata, final rounding, unavailability
 * accounting, etc. Applied at the end of the pipeline to turn full-year
 * tariffs into the final amounts owed.
 *
 * `daysWindow` contract: any transversal rule that iterates over dates
 * (`expandToDaysInYear`, day-by-day loops) MUST honour
 * `$context->daysWindow` when set (intersect the dates with the window).
 * This is required for correctness when the pipeline runs in segmented
 * mode via {@see FiscalSegmentedExecutor}: each
 * sub-execution sees the full contracts (to preserve per-contract
 * semantics like R-2024-021 LCD) but must only count days that fall
 * inside the current segment. See R-2024-002 and R-2024-008 for the
 * canonical implementation.
 */
interface TransversalRule extends FiscalRule
{
    public function apply(PipelineContext $context): PipelineContext;
}
