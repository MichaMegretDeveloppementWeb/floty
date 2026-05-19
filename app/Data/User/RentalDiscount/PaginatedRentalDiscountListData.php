<?php

declare(strict_types=1);

namespace App\Data\User\RentalDiscount;

use App\Data\Shared\Listing\PaginationMetaData;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server-side paginated result wrapper for the Rental Discounts index
 * (ADR-0020).
 *
 * Concrete DTO (rather than a generic `PaginatedListData<T>`) so the
 * generated TS type is the flat
 * `App.Data.User.RentalDiscount.PaginatedRentalDiscountListData`.
 */
#[TypeScript]
final class PaginatedRentalDiscountListData extends Data
{
    /**
     * @param  array<int, RentalDiscountListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, RentalDiscountListItemData>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            data: $paginator->items(),
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }
}
