<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use Illuminate\Validation\Rule;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Input DTO for the server-side declarations index (ADR-0020).
 *
 * Filters: `companyId`, `fiscalYear`, `status`, and `obsoleteOnly`
 * (returns only declarations where `is_obsolete = true`). Sort whitelist
 * covers `company | fiscalYear | reference | status | generatedAt`. No
 * text search since it adds little value here.
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
        return ['company', 'fiscalYear', 'reference', 'status', 'generatedAt'];
    }

    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'fiscalYear' => ['nullable', 'integer', 'between:2020,2099'],
            // `Rule::enum` keeps validation aligned with the property type
            // automatically as cases are added or removed.
            'status' => ['nullable', Rule::enum(FiscalDeclarationStatus::class)],
            'obsoleteOnly' => ['nullable', 'boolean'],
        ]);
    }
}
