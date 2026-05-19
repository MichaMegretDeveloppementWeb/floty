<?php

declare(strict_types=1);

namespace App\Actions\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Data\User\RentalDiscount\UpdateRentalDiscountData;
use App\Models\RentalDiscount;
use App\Services\RentalDiscount\RentalDiscountConflictService;
use Illuminate\Support\Facades\DB;

/**
 * Updates an existing rental discount after overlap validation.
 *
 * Pipeline (transaction):
 *   1. Guard against overlap (the current row is excluded via
 *      `excludeId`).
 *   2. Update scalar fields.
 *   3. Sync vehicles pivot (idempotent, replaces the whole list).
 *
 * `company_id` is not mutable; see {@see UpdateRentalDiscountData} for
 * the rationale (immutable invoice audit). The
 * `RentalDiscountObserver` flips `is_divergent` on the impacted
 * invoices when dates or rate change.
 */
final readonly class UpdateRentalDiscountAction
{
    public function __construct(
        private RentalDiscountWriteRepositoryInterface $writer,
        private RentalDiscountConflictService $conflicts,
    ) {}

    public function execute(RentalDiscount $discount, UpdateRentalDiscountData $data): RentalDiscount
    {
        $vehicleIds = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $data->vehicleIds ?? [])));

        $this->conflicts->assertNoConflict(
            companyId: $discount->company_id,
            startDate: $data->startDate,
            endDate: $data->endDate,
            vehicleIds: $vehicleIds,
            excludeId: $discount->id,
        );

        return DB::transaction(function () use ($discount, $data, $vehicleIds): RentalDiscount {
            $updated = $this->writer->update($discount, [
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'discount_basis_points' => $data->discountBasisPoints,
                'label' => $data->label,
                'notes' => $data->notes,
            ]);

            // sync() replaces the entire pivot: drops missing vehicles,
            // inserts new ones. Empty list = pivot purged ("all"
            // semantics).
            $this->writer->syncVehicles($updated, $vehicleIds);

            return $updated->fresh(['vehicles', 'company:id,short_code,legal_name,color'])
                ?? throw new \RuntimeException('Failed to reload rental discount after update.');
        });
    }
}
