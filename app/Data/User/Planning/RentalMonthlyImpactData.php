<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Impact of a synthetic contract on a company's monthly rent for one
 * calendar month.
 *
 * The user is asking "if I validate this rental, what will the company's
 * invoice look like?". Per affected month:
 *   - `existingNetCents`: current net rent (post-discounts) without the
 *     synthetic contract;
 *   - `newTotalCents`: new monthly total after addition (existing +
 *     synthetic contribution).
 *
 * Caveat: `existing + induced` assumes the synthetic contract has no
 * overlapping days with an existing one for the same company (which the
 * DateRangePicker enforces by blocking busy dates).
 */
#[TypeScript]
final class RentalMonthlyImpactData extends Data
{
    public function __construct(
        public int $year,
        public int $month,
        public int $existingNetCents,
        public int $newTotalCents,
    ) {}
}
