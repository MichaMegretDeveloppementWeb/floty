<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server-side paginated result wrapper for the Invoices index (ADR-0020).
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
