<?php

declare(strict_types=1);

namespace App\Services\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\RentalDiscount\PaginatedRentalDiscountListData;
use App\Data\User\RentalDiscount\RentalDiscountData;
use App\Data\User\RentalDiscount\RentalDiscountIndexQueryData;
use App\Data\User\RentalDiscount\RentalDiscountListItemData;
use App\Models\RentalDiscount;
use Carbon\CarbonImmutable;

/**
 * Composes RentalDiscount DTOs from raw models (ADR-0013 R3).
 */
final readonly class RentalDiscountQueryService
{
    public function __construct(
        private RentalDiscountReadRepositoryInterface $reader,
    ) {}

    /**
     * Server-side Index (ADR-0020). Delegates the SQL query to the
     * repository and maps the models to presentation DTOs.
     */
    public function listPaginated(RentalDiscountIndexQueryData $query): PaginatedRentalDiscountListData
    {
        $paginator = $this->reader->paginateForIndex($query);
        $today = CarbonImmutable::now()->startOfDay();

        $items = array_map(
            static fn (RentalDiscount $discount): RentalDiscountListItemData => RentalDiscountListItemData::fromModel(
                $discount,
                $today,
            ),
            $paginator->items(),
        );

        return new PaginatedRentalDiscountListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Detail for the Show page. Returns `null` when no discount
     * matches (the controller raises a 404).
     */
    public function detail(int $id): ?RentalDiscountData
    {
        $discount = $this->reader->findByIdForShow($id);
        if ($discount === null) {
            return null;
        }

        return RentalDiscountData::fromModel($discount);
    }

    /**
     * Three counters (active / planned / expired) for the Index
     * header banner, relative to today.
     *
     * @return array{active: int, planned: int, expired: int}
     */
    public function statsForIndex(): array
    {
        return $this->reader->statsForIndex(CarbonImmutable::now()->toDateString());
    }
}
