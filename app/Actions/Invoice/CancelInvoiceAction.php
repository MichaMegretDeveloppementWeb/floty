<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Models\Invoice;
use App\Services\Invoice\InvoicePdfStorage;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an emitted invoice. Hard-deletes the row and removes the
 * PDF.
 *
 * Use case: explicit user cancellation (invoice emitted by mistake,
 * duplicate, ...). Different from the regeneration pattern
 * ({@see RegenerateInvoiceAction}) which soft-deletes and archives
 * the PDF.
 *
 * The PDF is intentionally deleted AFTER the DB commit so a failing
 * commit cannot leave an Invoice row pointing at a missing file. The
 * filesystem delete is idempotent.
 */
final readonly class CancelInvoiceAction
{
    public function __construct(
        private InvoiceWriteRepositoryInterface $writer,
        private InvoicePdfStorage $storage,
    ) {}

    public function execute(Invoice $invoice): void
    {
        $pdfPath = $invoice->pdf_path;

        DB::transaction(function () use ($invoice): void {
            // `forceDelete` (not `delete`): explicit cancellation wipes
            // the invoice entirely. Historical preservation is reserved
            // for the regeneration pattern.
            $this->writer->forceDelete($invoice);
        });

        $this->storage->delete($pdfPath);
    }
}
