<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One-line per-year summary of invoices left to generate for a company
 * (Company Show "À faire" panel).
 *
 * A "pending invoice" = month where `daysUsed > 0` AND tariff present AND
 * no active `Invoice` row on the (company, year, month) tuple. Months are
 * excluded when:
 *   - no contract exists (nothing to bill)
 *   - missing tariff blocks the month (user must first fill the vehicle
 *     yearly tariff; messaging owned by the Billing tab, not this panel)
 *   - the month is future (current or later months are not billable until
 *     elapsed)
 */
#[TypeScript]
final class PendingInvoiceYearData extends Data
{
    public function __construct(
        public int $fiscalYear,
        public int $missingInvoicesCount,
    ) {}
}
