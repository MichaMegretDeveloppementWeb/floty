<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Operational pending tasks shown on the Dashboard.
 *
 * For declarations, `pendingDeclarationsCount` counts every
 * `(company, year)` couple awaiting action; `pendingDeclarations`
 * carries the top 5 ranked by urgency (overdue first).
 *
 * For invoices, `pendingInvoicesMonthlyTotal` sums all monthly invoices
 * left to generate (a single line can stand for up to 12 monthly
 * invoices per `(company, year)`); `pendingInvoices` carries the top 5
 * lines ordered by year ascending.
 */
#[TypeScript]
final class DashboardPendingTasksData extends Data
{
    /**
     * @param  list<DashboardPendingDeclarationItemData>  $pendingDeclarations
     * @param  list<DashboardPendingInvoiceItemData>  $pendingInvoices
     */
    public function __construct(
        public int $pendingDeclarationsCount,
        #[DataCollectionOf(DashboardPendingDeclarationItemData::class)]
        public array $pendingDeclarations,
        public int $pendingInvoicesMonthlyTotal,
        #[DataCollectionOf(DashboardPendingInvoiceItemData::class)]
        public array $pendingInvoices,
    ) {}

    public static function noPending(): self
    {
        return new self(
            pendingDeclarationsCount: 0,
            pendingDeclarations: [],
            pendingInvoicesMonthlyTotal: 0,
            pendingInvoices: [],
        );
    }
}
