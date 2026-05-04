<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Data\Shared\Listing\PaginationMetaData;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Wrapper de retour pour l'Index Drivers server-side (cf. ADR-0020).
 *
 * Choix DTO concret (vs générique `PaginatedListData<T>`) : produit un
 * type TS plat `App.Data.User.Driver.PaginatedDriverListData` lisible
 * côté front sans cast.
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
