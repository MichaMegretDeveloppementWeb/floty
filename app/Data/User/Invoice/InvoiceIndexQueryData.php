<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Query input for the server-side Invoices index (ADR-0020).
 *
 * Filters:
 *   - `companyId`: exact company match
 *   - `year` / `month`: exact period (one civil month)
 *   - `divergentOnly`: restrict to rows with `is_divergent = true` (the
 *     materialised flag is set by observers, see
 *     {@see App\Services\Invoice\InvoiceDivergenceFlagger}). Native SQL
 *     filter, no PHP post-processing.
 *   - `includeObsolete`: include soft-deleted older versions (defaults to
 *     `false`; UI keeps the listing dense by hiding them).
 *
 * Allowed sort keys: `invoiceNumber | company | period | totalHt |
 * generatedAt` (all translatable to pure SQL).
 */
#[TypeScript]
final class InvoiceIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?int $companyId = null,
        public ?int $year = null,
        public ?int $month = null,
        public bool $divergentOnly = false,
        public bool $includeObsolete = false,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        ?string $sortKey = null,
        SortDirection $sortDirection = SortDirection::Desc,
    ) {
        parent::__construct($page, $perPage, $search, $sortKey, $sortDirection);
    }

    public static function allowedSortKeys(): array
    {
        return ['invoiceNumber', 'company', 'period', 'totalHt', 'generatedAt'];
    }

    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'year' => ['nullable', 'integer', 'between:2020,2099'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'divergentOnly' => ['nullable', 'boolean'],
            'includeObsolete' => ['nullable', 'boolean'],
        ]);
    }
}
