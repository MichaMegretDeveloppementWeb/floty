<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceLine;

/**
 * Invoice writes · creation only. No update: per the immutability
 * doctrine, an emitted invoice is frozen. The only allowed mutation is
 * `delete` (cascading on the lines).
 */
interface InvoiceWriteRepositoryInterface
{
    /**
     * Persists an invoice. Lines are created separately via
     * {@see persistLines} in the same transaction (cf.
     * `GenerateInvoiceAction`).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persist(array $attributes): Invoice;

    /**
     * Persists the lines attached to an invoice. Bulk insert.
     *
     * @param  list<array<string, mixed>>  $linesAttributes
     * @return list<InvoiceLine>
     */
    public function persistLines(int $invoiceId, array $linesAttributes): array;

    /**
     * Soft-delete (Invoice uses `SoftDeletes`). Kept for regeneration ·
     * cf. {@see RegenerateInvoiceAction}.
     */
    public function delete(Invoice $invoice): void;

    /**
     * Hard-delete with cascading on the lines. Used by
     * {@see CancelInvoiceAction} for explicit cancellation, which
     * fully erases the invoice · distinct from the regeneration
     * soft-delete that materialises history.
     */
    public function forceDelete(Invoice $invoice): void;

    /**
     * Bulk conditional UPDATE setting `is_divergent = true` on
     * invoices of the company whose `(year, month)` matches one of the
     * tuples. Skips already-divergent invoices (idempotence + write
     * economy).
     *
     * Returns the number of rows flipped.
     *
     * Doctrinal note: `is_divergent` is an observability metadata (not
     * a frozen snapshot field per ADR-0008) · its mutation
     * post-emission is legitimate.
     *
     * @param  list<array{year:int,month:int}>  $tuples
     */
    public function flagDivergentForCompanyAndTuples(int $companyId, array $tuples): int;

    /**
     * Bulk conditional UPDATE setting `is_divergent = true` on all
     * invoices of the given `year` that belong to companies which had
     * an active contract on the vehicle crossing that year (case of a
     * vehicle rate change).
     *
     * The vehicle → companies pivot is computed via an embedded
     * subquery in the UPDATE to remain atomic SQL-side (one DB
     * round-trip). Constrained to `deleted_at IS NULL` because the
     * subquery uses the low-level Query Builder which bypasses the
     * Eloquent SoftDeletes scope.
     *
     * Returns the number of invoices flipped.
     */
    public function flagDivergentForVehiclePricingYear(int $vehicleId, int $year): int;
}
