<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Invoice\InvoiceData;
use App\Data\User\Invoice\InvoiceDivergenceData;
use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Data\User\Invoice\InvoiceListItemData;
use App\Data\User\Invoice\PaginatedInvoiceListData;
use App\Models\Invoice;

/**
 * Query service for the Invoice domain. Composes the models returned
 * by the repository into the DTOs exposed to Inertia.
 *
 * ADR-0013 compliant · no SQL here, only mapping. Index divergence is
 * read from the materialised `invoices.is_divergent` column maintained
 * by observers ({@see InvoiceDivergenceFlagger}). The Show fiche keeps
 * `InvoiceDivergenceChecker` to feed the banner with precise values
 * (snapshot vs current).
 */
final readonly class InvoiceQueryService
{
    public function __construct(
        private InvoiceReadRepositoryInterface $repository,
        private InvoiceDivergenceChecker $divergenceChecker,
    ) {}

    public function listPaginated(InvoiceIndexQueryData $query): PaginatedInvoiceListData
    {
        $paginator = $this->repository->paginateForIndex($query);

        $items = array_map(
            static fn (Invoice $invoice): InvoiceListItemData => InvoiceListItemData::fromModel($invoice),
            $paginator->items(),
        );

        return new PaginatedInvoiceListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    public function findInvoiceData(int $id): ?InvoiceData
    {
        $invoice = $this->repository->findById($id);

        if ($invoice === null) {
            return null;
        }

        $predecessor = $this->repository->findPredecessor($id);

        $historyChain = $this->repository->findHistoryChainFor($invoice);

        // Divergence is served via `Inertia::defer` from Show so the
        // mount is not blocked by a full BillingCalculator pass.

        return InvoiceData::fromModel($invoice, $predecessor, $historyChain);
    }

    /**
     * Snapshot vs current contractual reality, served via
     * `Inertia::defer` from Show. Returns `null` for obsolete invoices
     * (`deleted_at !== null`) or missing ids · the frontend then hides
     * the divergence banner (obsolete versions are frozen at
     * regeneration time).
     */
    public function divergenceForInvoice(int $id): ?InvoiceDivergenceData
    {
        $invoice = $this->repository->findById($id);
        if ($invoice === null || $invoice->deleted_at !== null) {
            return null;
        }

        return $this->divergenceChecker->check($invoice);
    }
}
