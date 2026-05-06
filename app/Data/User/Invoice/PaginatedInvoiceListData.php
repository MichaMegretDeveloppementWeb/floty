<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Wrapper de retour pour l'Index Invoices server-side (Phase 14.F V1.2,
 * cf. ADR-0020).
 */
#[TypeScript]
final class PaginatedInvoiceListData extends Data
{
    /**
     * @param  array<int, InvoiceListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
