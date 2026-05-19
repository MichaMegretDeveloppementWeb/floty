<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Regenerates an emitted invoice.
 *
 * Semantics: the previous invoice is NOT deleted; it is soft-deleted,
 * marked obsolete and its `superseded_by_id` points to the new
 * invoice. Its PDF stays on disk for the audit trail (art. 286 quater
 * CGI). The historical chain is fully reconstructible:
 * `v1.superseded_by → v2.superseded_by → v3 (current)`.
 *
 * Numbering: the new invoice gets a brand new sequential number in the
 * chronological sequence (no "bis" suffix), as required by art. 242
 * nonies A annexe II CGI (unbroken numbering) and the BOFiP doctrine
 * BOI-TVA-DECLA-30-20-20-10 (a rectifying invoice keeps a new number
 * and mentions the replacement).
 *
 * Filesystem coherence: the historical PDF is preserved. If `Generate`
 * throws, the transaction rolls back and the previous invoice is
 * restored; no filesystem cleanup needed.
 */
final readonly class RegenerateInvoiceAction
{
    public function __construct(
        private GenerateInvoiceAction $generate,
    ) {}

    /**
     * @param  array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}  $issuer
     */
    public function execute(Invoice $invoice, int $generatedByUserId, array $issuer): Invoice
    {
        $companyId = (int) $invoice->company_id;
        $year = (int) $invoice->year;
        $month = (int) $invoice->month;

        return DB::transaction(function () use ($invoice, $generatedByUserId, $issuer, $companyId, $year, $month): Invoice {
            // 1. Soft-delete the previous invoice and mark the
            //    obsolescence instant. `is_divergent` is left as-is;
            //    it was an instantaneous divergence flag, distinct
            //    from the definitive replacement marker.
            $invoice->obsolete_at = Carbon::now();
            $invoice->save();
            $invoice->delete();

            // 2. Generate the new invoice (next sequential number in
            //    the chronological sequence, complying with French
            //    legal numbering requirements).
            $newInvoice = $this->generate->execute(
                companyId: $companyId,
                year: $year,
                month: $month,
                generatedByUserId: $generatedByUserId,
                issuer: $issuer,
            );

            // 3. Historical chain: link the old row to the new one.
            //    Done via QueryBuilder to bypass the SoftDeletes scope
            //    (the model is soft-deleted and would not re-save
            //    without `restore()`).
            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update(['superseded_by_id' => $newInvoice->id]);

            return $newInvoice;
        });
    }
}
