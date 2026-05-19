<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Invoice;

use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Invoice reads · slim interface per ADR-0013.
 */
interface InvoiceReadRepositoryInterface
{
    /**
     * Includes soft-deleted invoices (obsolete versions after
     * regeneration) so the Show page can navigate any historical
     * version. The UI shows a "Replaced by #XXX" banner on the
     * obsolete ones.
     */
    public function findById(int $id): ?Invoice;

    /**
     * Retrieves the invoice that was replaced by the one with id
     * `$invoiceId` via a regeneration (the predecessor). Returns
     * `null` for invoices that did not replace a prior version.
     * Includes soft-deleted · a predecessor is by construction
     * obsolete.
     */
    public function findPredecessor(int $invoiceId): ?Invoice;

    /**
     * Rebuilds the full version chain for the (company × year × month)
     * of a given invoice. Includes the current invoice itself and all
     * obsolete (soft-deleted) versions. No order guaranteed · sorting
     * is done by the front component (`InvoiceHistoryTimeline`).
     *
     * @return list<Invoice>
     */
    public function findHistoryChainFor(Invoice $invoice): array;

    /**
     * Application-level uniqueness lookup (company × year × month).
     * Used by {@see App\Actions\Invoice\GenerateInvoiceAction} to
     * reject regeneration before hitting the UNIQUE constraint in DB.
     */
    public function findForCompanyYearMonth(int $companyId, int $year, int $month): ?Invoice;

    /**
     * Map of emitted invoices for a (company × year), indexed by
     * calendar month. Used by the monthly summary on the company page
     * to:
     *   - switch the "Generate" button → "View #YYYY-MM-NNNN" link
     *   - detect a divergence between invoice and reality (adding /
     *     editing a contract post-emission) by comparing the frozen
     *     snapshot (`totalHtCents`, `invoicedDaysUsed`) to the dynamic
     *     recompute
     *
     * Single query (no N+1 on the 12 months).
     *
     * `grossTotalCents` and `totalDiscountCents` are included to
     * expose the gross/discount detail of the emitted invoice on
     * billing screens.
     *
     * @return array<int, array{id: int, invoiceNumber: string, totalHtCents: int, invoicedDaysUsed: int, grossTotalCents: int, totalDiscountCents: int}> map[month] => snapshot
     */
    public function findExistingByMonthForCompanyYear(int $companyId, int $year): array;

    /**
     * Highest sequence number already assigned for a (year, month).
     * Used to generate the next `invoice_number` (no race condition
     * thanks to the application-level UNIQUE constraint + the Action
     * transaction).
     *
     * Returns 0 if no invoice yet for that month.
     */
    public function maxSequenceForYearMonth(int $year, int $month): int;

    /**
     * Server-side paginated list for the Invoices Index (ADR-0020).
     *
     * The `divergentOnly` filter is applied in raw SQL (`WHERE
     * is_divergent = 1`) · the materialised flag is set by observers,
     * removing the N+1 `BillingCalculator::calculate` that degraded
     * the Index.
     *
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateForIndex(InvoiceIndexQueryData $query): LengthAwarePaginator;

    /**
     * Returns true iff at least one invoice exists. Used by the Index
     * to distinguish an intrinsically empty table from an active
     * filter returning zero rows.
     */
    public function existsAny(): bool;

    /**
     * Bounds of years covered by emitted invoices. Used to drive the
     * Year filter options list on the Index: every year between the
     * oldest invoice and the current year (or the latest year if
     * higher) is displayed.
     *
     * Returns `null` if there is no invoice (empty table case).
     *
     * @return array{min: int, max: int}|null
     */
    public function findYearBounds(): ?array;
}
