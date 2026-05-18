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
 * Composition des DTOs RentalDiscount à partir des Models bruts
 * (Lot 4 du chantier RentalDiscount · ADR-0013 R3).
 *
 * Pattern aligné avec `DriverQueryService`, `ContractQueryService`, etc.
 */
final readonly class RentalDiscountQueryService
{
    public function __construct(
        private RentalDiscountReadRepositoryInterface $reader,
    ) {}

    /**
     * Index server-side (cf. ADR-0020). Délègue la query SQL au repo
     * puis mappe les models en DTO de présentation.
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
     * Détail pour la page Show. Retourne `null` si l'id ne correspond
     * à aucune réduction (le controller throw 404).
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
     * Stats pour le bandeau supérieur de la page Index (3 compteurs ·
     * actives / planifiées / expirées · par rapport à la date du jour).
     *
     * @return array{active: int, planned: int, expired: int}
     */
    public function statsForIndex(): array
    {
        return $this->reader->statsForIndex(CarbonImmutable::now()->toDateString());
    }
}
