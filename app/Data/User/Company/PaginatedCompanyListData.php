<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server-side paginated wrapper for the Companies Index (ADR-0020).
 */
#[TypeScript]
final class PaginatedCompanyListData extends Data
{
    /**
     * @param  array<int, CompanyListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
