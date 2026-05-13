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
 * mapping. Depuis T6 (Phase 14.R), la divergence pour l'Index est lue
 * directement sur la colonne matérialisée `invoices.is_divergent`
 * (flag posé par observers, cf. {@see InvoiceDivergenceFlagger}).
 * La fiche Show conserve `InvoiceDivergenceChecker` pour fournir au
 * banner les valeurs précises (snapshot vs courant).
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

        // Predecessor : si cette facture remplace une version antérieure
        // (chaînage `superseded_by_id` inverse), on l'expose pour le
        // bandeau « Remplace #YYYY ».
        $predecessor = $this->repository->findPredecessor($id);

        // Chaîne historique complète des versions du même couple
        // (company × year × month) · alimente la timeline UI.
        $historyChain = $this->repository->findHistoryChainFor($invoice);

        // Divergence : non pertinente pour les versions obsolètes
        // (elles sont figées dans leur état au moment de la régénération).
        // On évite l'appel coûteux à `BillingCalculator` qui pourrait
        // d'ailleurs lever `MissingPricingException` rétroactivement.
        $divergence = $invoice->deleted_at === null
            ? $this->divergenceChecker->check($invoice)
            : null;

        return InvoiceData::fromModel($invoice, $divergence, $predecessor, $historyChain);
    }
}
