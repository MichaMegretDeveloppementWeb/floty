<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Input DTO for the server-side Drivers Index (ADR-0020).
 *
 * `companyId` filters on currently-open memberships (`left_at IS NULL`);
 * closed memberships are excluded.
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

    /**
     * @return list<string>
     */
    public static function allowedSortKeys(): array
    {
        return ['fullName', 'contractsCount', 'activeCompaniesCount'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'activityStatus' => ['nullable', 'string', 'in:active,inactive'],
            'contractsScope' => ['nullable', 'string', 'in:with,without'],
        ]);
    }
}
