<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Data\Shared\Listing\PaginationMetaData;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server-side paginated wrapper for the Drivers Index (ADR-0020). Concrete
 * DTO (over a generic) produces a flat TS type the frontend consumes without
 * casts.
 */
#[TypeScript]
final class PaginatedDriverListData extends Data
{
    /**
     * @param  array<int, DriverListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, DriverListItemData>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            data: $paginator->items(),
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }
}
