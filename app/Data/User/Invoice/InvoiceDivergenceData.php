<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Comparison snapshot between an issued invoice (frozen) and the current
 * recalculated reality. Signals to the user that contractual data changed
 * after issuance and regeneration is likely needed.
 *
 * `currentDaysUsed` / `currentTotalCents` may be `null` if the current
 * recompute is blocked (e.g. missing yearly tariff for a vehicle that was
 * present at issuance); `hasDivergence` is then necessarily `true`.
 */
#[TypeScript]
final class InvoiceDivergenceData extends Data
{
    public function __construct(
        public bool $hasDivergence,
        /** Frozen snapshot at issuance (sum of line `days_used`). */
        public int $invoicedDaysUsed,
        /** Frozen snapshot at issuance (`total_ht_cents`). */
        public int $invoicedTotalCents,
        /** Recompute now over the current contractual scope. */
        public ?int $currentDaysUsed,
        /** Recompute now over the current contractual scope. */
        public ?int $currentTotalCents,
    ) {}
}
