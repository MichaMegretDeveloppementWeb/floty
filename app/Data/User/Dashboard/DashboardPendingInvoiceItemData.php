<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dashboard "invoice pending generation" item: one row per
 * `(company, year)` couple that still has at least one monthly invoice
 * left to generate. `missingInvoicesCount` is the number of months
 * eligible for generation (past months with usage + a rate + not yet
 * invoiced).
 */
#[TypeScript]
final class DashboardPendingInvoiceItemData extends Data
{
    public function __construct(
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        public int $missingInvoicesCount,
    ) {}
}
