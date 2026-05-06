<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Invoice\InvoiceData;
use App\Data\User\Invoice\InvoiceIndexQueryData;
use App\Data\User\Invoice\InvoiceListItemData;
use App\Data\User\Invoice\PaginatedInvoiceListData;
use App\Models\Invoice;

/**
 * Service Query du domaine Invoice (Phase 14.F V1.2). Compose les
 * Models retournés par le repo en DTOs exposés à Inertia.
 *
 * Conforme ADR-0013 : zéro SQL ici, uniquement transformation +
 * mapping.
 */
final readonly class InvoiceQueryService
{
    public function __construct(
        private InvoiceReadRepositoryInterface $repository,
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

        return InvoiceData::fromModel($invoice);
    }
}
