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
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service Query du domaine Invoice (Phase 14.F V1.2). Compose les
 * Models retournés par le repo en DTOs exposés à Inertia, et enrichit
 * chaque facture avec un signal de divergence (cf. 14.I+).
 *
 * Conforme ADR-0013 : zéro SQL ici, uniquement transformation +
 * mapping + post-traitement PHP pour le filtre `divergentOnly` (qui
 * nécessite un calcul par facture, non exprimable en SQL natif).
 */
final readonly class InvoiceQueryService
{
    public function __construct(
        private InvoiceReadRepositoryInterface $repository,
        private InvoiceDivergenceChecker $divergenceChecker,
    ) {}

    public function listPaginated(InvoiceIndexQueryData $query): PaginatedInvoiceListData
    {
        if ($query->divergentOnly) {
            return $this->listPaginatedDivergentOnly($query);
        }

        // Cas standard : pagination SQL native + check divergence par
        // ligne (1 calcul `BillingCalculator::calculate` par facture
        // de la page courante, acceptable jusqu'à perPage=100).
        $paginator = $this->repository->paginateForIndex($query);

        $items = array_map(
            fn (Invoice $invoice): InvoiceListItemData => InvoiceListItemData::fromModel(
                $invoice,
                $this->divergenceChecker->check($this->ensureLinesLoaded($invoice))->hasDivergence,
            ),
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

        return InvoiceData::fromModel(
            $invoice,
            $this->divergenceChecker->check($invoice),
        );
    }

    /**
     * Pagination en post-traitement PHP : on charge toutes les
     * factures matchant les filtres SQL, on filtre celles divergentes,
     * puis on coupe pour la page demandée. Acceptable pour V1.2 démo
     * (max ~100 factures). Pour V2, à réimplémenter avec une vue SQL
     * matérialisée si le volume devient significatif.
     */
    private function listPaginatedDivergentOnly(InvoiceIndexQueryData $query): PaginatedInvoiceListData
    {
        $all = $this->repository->findAllMatching($query);

        $filtered = $all
            ->map(fn (Invoice $invoice): array => [
                'invoice' => $invoice,
                'check' => $this->divergenceChecker->check($invoice),
            ])
            ->filter(static fn (array $entry): bool => $entry['check']->hasDivergence)
            ->values();

        $total = $filtered->count();
        $perPage = $query->perPage;
        $currentPage = max(1, $query->page);
        $offset = ($currentPage - 1) * $perPage;

        $pageItems = $filtered
            ->slice($offset, $perPage)
            ->map(static fn (array $entry): InvoiceListItemData => InvoiceListItemData::fromModel(
                $entry['invoice'],
                hasDivergence: true,
            ))
            ->values()
            ->all();

        $paginator = new LengthAwarePaginator(
            items: $pageItems,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
        );

        return new PaginatedInvoiceListData(
            data: $pageItems,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    private function ensureLinesLoaded(Invoice $invoice): Invoice
    {
        if (! $invoice->relationLoaded('lines')) {
            $invoice->load('lines');
        }

        return $invoice;
    }
}
