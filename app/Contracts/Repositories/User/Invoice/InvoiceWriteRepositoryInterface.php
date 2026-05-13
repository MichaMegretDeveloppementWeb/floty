<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceLine;

/**
 * Écritures Invoice · création seulement. Pas d'update : conformément
 * à la doctrine immuabilité (Phase 14.E V1.2), une facture émise est
 * figée. La seule mutation autorisée est `delete` (cascade des lignes).
 */
interface InvoiceWriteRepositoryInterface
{
    /**
     * Persiste une facture en base. Les lignes sont créées séparément
     * via {@see persistLines} dans la même transaction (cf.
     * `GenerateInvoiceAction`).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persist(array $attributes): Invoice;

    /**
     * Persiste les lignes attachées à une facture. Bulk insert.
     *
     * @param  list<array<string, mixed>>  $linesAttributes
     * @return list<InvoiceLine>
     */
    public function persistLines(int $invoiceId, array $linesAttributes): array;

    /**
     * Suppression soft (Invoice utilise `SoftDeletes`). Conservée pour
     * la régénération · cf. {@see RegenerateInvoiceAction}.
     */
    public function delete(Invoice $invoice): void;

    /**
     * Suppression physique (hard delete) avec cascade des lignes. Utilisé
     * par {@see CancelInvoiceAction} pour l'annulation explicite, qui
     * effacent intégralement la facture · à distinguer du soft-delete
     * de la régénération qui matérialise l'historique.
     */
    public function forceDelete(Invoice $invoice): void;
}
