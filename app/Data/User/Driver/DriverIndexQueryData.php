<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Drivers server-side (cf. ADR-0020).
 *
 * Filtres :
 *  - `companyId` : drivers ACTIVEMENT rattachés à cette entreprise
 *    (membership ouvert, `driver_company.left_at IS NULL`). Les
 *    rattachements clos sont exclus.
 *  - `activityStatus` : 'active' = au moins un membership ouvert ;
 *    'inactive' = aucun membership ouvert
 *  - `contractsScope` : 'with' = au moins un contrat ; 'without' = aucun
 *
 * Whitelist sortKey : `fullName | contractsCount | activeCompaniesCount`.
 */
#[TypeScript]
final class DriverIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?int $companyId = null,
        public ?string $activityStatus = null,
        public ?string $contractsScope = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        ?string $sortKey = null,
        SortDirection $sortDirection = SortDirection::Asc,
    ) {
        parent::__construct($page, $perPage, $search, $sortKey, $sortDirection);
    }

    public static function allowedSortKeys(): array
    {
        return ['fullName', 'contractsCount', 'activeCompaniesCount'];
    }

    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'activityStatus' => ['nullable', 'string', 'in:active,inactive'],
            'contractsScope' => ['nullable', 'string', 'in:with,without'],
        ]);
    }
}
