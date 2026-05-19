<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cumulative "since-inception" statistics for a company, all years combined.
 */
#[TypeScript]
final class CompanyLifetimeStatsData extends Data
{
    public function __construct(
        /** Sum of contract days across all fiscal years. */
        public int $daysUsed,
        /** Total count of active (non-soft-deleted) contracts across all years. */
        public int $contractsCount,
        /** Sum of computed taxes across all fiscal years (€, rounded to 2 decimals). */
        public float $taxesGenerated,
        /** Cumulated billed rent across all fiscal years; null until billing V1.2 ships. */
        public ?float $rentTotal,
    ) {}
}
