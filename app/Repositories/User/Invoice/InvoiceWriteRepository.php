<?php

declare(strict_types=1);

namespace App\Repositories\User\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Models\Invoice;
use App\Models\InvoiceLine;

final class InvoiceWriteRepository implements InvoiceWriteRepositoryInterface
{
    public function persist(array $attributes): Invoice
    {
        $invoice = new Invoice;
        $invoice->fill($attributes);
        $invoice->save();

        return $invoice;
    }

    public function persistLines(int $invoiceId, array $linesAttributes): array
    {
        $created = [];
        foreach ($linesAttributes as $attrs) {
            $line = new InvoiceLine;
            $line->fill([...$attrs, 'invoice_id' => $invoiceId]);
            $line->save();
            $created[] = $line;
        }

        return $created;
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }
}
