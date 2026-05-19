<?php

declare(strict_types=1);

namespace App\Actions\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Data\User\RentalDiscount\StoreRentalDiscountData;
use App\Models\RentalDiscount;
use App\Services\RentalDiscount\RentalDiscountConflictService;
use Illuminate\Support\Facades\DB;

/**
 * Creates a rental discount after overlap validation.
 *
 * Pipeline (transaction):
 *   1. Guard against overlapping discounts via
 *      {@see RentalDiscountConflictService::assertNoConflict}.
 *   2. Persist the parent discount with `created_by_user_id`.
 *   3. Sync targeted vehicles (empty list = "all vehicles" semantics).
 *
 * The `RentalDiscountObserver` flags impacted invoices as divergent.
 */
final readonly class CreateRentalDiscountAction
{
    public function __construct(
        private RentalDiscountWriteRepositoryInterface $writer,
        private RentalDiscountConflictService $conflicts,
    ) {}

    public function execute(StoreRentalDiscountData $data, int $createdByUserId): RentalDiscount
    {
        $vehicleIds = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $data->vehicleIds ?? [])));

        $this->conflicts->assertNoConflict(
            companyId: $data->companyId,
            startDate: $data->startDate,
            endDate: $data->endDate,
            vehicleIds: $vehicleIds,
        );

        return DB::transaction(function () use ($data, $vehicleIds, $createdByUserId): RentalDiscount {
            $discount = $this->writer->create([
                'company_id' => $data->companyId,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'discount_basis_points' => $data->discountBasisPoints,
                'label' => $data->label,
                'notes' => $data->notes,
                'created_by_user_id' => $createdByUserId,
            ]);

            if ($vehicleIds !== []) {
                $this->writer->syncVehicles($discount, $vehicleIds);
            }

            return $discount->fresh(['vehicles', 'company:id,short_code,legal_name,color'])
                ?? throw new \RuntimeException('Failed to reload rental discount after persist.');
        });
    }
}
