<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Rental Discounts server-side (cf. ADR-0020).
 *
 * Filtres :
 *  - `companyId` : réductions d'une entreprise précise
 *  - `status` : `'active' | 'planned' | 'expired'` calculé par rapport
 *    à la date du jour côté SQL (`start_date <= today AND end_date >=
 *    today` → active ; `start_date > today` → planned ; `end_date <
 *    today` → expired).
 *
 * Whitelist sortKey : `company | period | discount | createdAt`.
 *
 * Sécurité · la whitelist `sortKey` empêche toute injection SQL via
 * `orderBy($_GET['sortKey'])`.
 */
#[TypeScript]
final class RentalDiscountIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?int $companyId = null,
        public ?string $status = null,
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
        return ['company', 'period', 'discount', 'createdAt'];
    }

    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'status' => ['nullable', 'string', 'in:active,planned,expired'],
        ]);
    }
}
