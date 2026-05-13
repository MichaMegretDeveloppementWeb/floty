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
 *   - `divergentOnly` : ne retourne que les factures avec
 *     `is_divergent = true` (flag matérialisé posé par observers, cf.
 *     {@see App\Services\Invoice\InvoiceDivergenceFlagger}). Filtre
 *     SQL natif depuis T6 / Phase 14.R, plus de post-traitement PHP.
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
        public bool $divergentOnly = false,
        /**
         * Inclut les versions obsolètes (factures soft-deletées après
         * régénération) dans la liste. Défaut `false` · la liste cache
         * les anciennes versions par défaut pour rester dense (12
         * factures actives / an / entreprise vs déclarations N=1).
         */
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
