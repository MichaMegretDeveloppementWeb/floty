<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Invoices server-side (cf. ADR-0020).
 *
 * Filtres :
 *   - `companyId` : filtre exact match sur l'entreprise
 *   - `year` / `month` : période exacte (un mois civil)
 *
 * Whitelist sortKey : `invoiceNumber | company | period | totalHt |
 * generatedAt`. Toutes traduisibles en SQL pure.
 */
#[TypeScript]
final class InvoiceIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?int $companyId = null,
        public ?int $year = null,
        public ?int $month = null,
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
        ]);
    }
}
