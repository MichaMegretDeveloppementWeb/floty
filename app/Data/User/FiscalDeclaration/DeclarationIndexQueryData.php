<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Déclarations server-side (Phase 11 D4,
 * cf. ADR-0020).
 *
 * Filtres :
 *   - `companyId` : filtre exact match sur l'entreprise
 *   - `fiscalYear` : année exacte
 *   - `status` : filtre exact (draft / deferred / generated)
 *   - `obsoleteOnly` : ne retourne que les déclarations `is_obsolete = true`
 *
 * Whitelist sortKey : `company | fiscalYear | status | generatedAt`.
 * Pas de search texte (peu utile sur les déclarations).
 */
#[TypeScript]
final class DeclarationIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?int $companyId = null,
        public ?int $fiscalYear = null,
        public ?FiscalDeclarationStatus $status = null,
        public bool $obsoleteOnly = false,
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
        return ['company', 'fiscalYear', 'status', 'generatedAt'];
    }

    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'fiscalYear' => ['nullable', 'integer', 'between:2020,2099'],
            'status' => ['nullable', 'string', 'in:draft,deferred,generated'],
            'obsoleteOnly' => ['nullable', 'boolean'],
        ]);
    }
}
