<?php

declare(strict_types=1);

namespace App\Actions\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Data\User\RentalDiscount\StoreRentalDiscountData;
use App\Models\RentalDiscount;
use App\Services\RentalDiscount\RentalDiscountConflictService;
use Illuminate\Support\Facades\DB;

/**
 * Crée une réduction commerciale après validation chevauchement
 * (Lot 4 du chantier RentalDiscount).
 *
 * **Pipeline (transaction)** ·
 *   1. Garde-fou non-chevauchement via
 *      {@see RentalDiscountConflictService::assertNoConflict} ·
 *      throw `RentalDiscountOverlapException` si conflit.
 *   2. Persiste la `RentalDiscount` parent + `created_by_user_id`.
 *   3. Synchronise les véhicules ciblés (peut être vide = sémantique
 *      « tous les véhicules », pivot vide).
 *
 * Le wrapping en `DB::transaction` garantit que la création parent +
 * sync pivot sont atomiques · pas d'orphan en cas d'échec d'une étape.
 * L'observer `RentalDiscountObserver` (Lot 3) est déclenché par
 * `create()` ; il marque divergentes les factures de la company sur
 * la période de la nouvelle réduction.
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
