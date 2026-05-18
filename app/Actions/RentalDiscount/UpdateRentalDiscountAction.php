<?php

declare(strict_types=1);

namespace App\Actions\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Data\User\RentalDiscount\UpdateRentalDiscountData;
use App\Models\RentalDiscount;
use App\Services\RentalDiscount\RentalDiscountConflictService;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour une réduction commerciale existante après validation
 * chevauchement (Lot 4 du chantier RentalDiscount).
 *
 * **Pipeline (transaction)** ·
 *   1. Garde-fou non-chevauchement (exclut la réduction en cours
 *      d'édition via `$excludeId`).
 *   2. Update les champs scalaires.
 *   3. Sync pivot véhicules (sync = idempotent, supprime + insère).
 *
 * **Pas de changement de `company_id`** · cf. doc de
 * {@see UpdateRentalDiscountData}. La doctrine projet veut qu'on crée
 * une nouvelle réduction plutôt que de muter le rattachement (audit
 * immuable factures).
 *
 * L'observer `RentalDiscountObserver` (Lot 3) flippe `is_divergent`
 * sur les factures de l'ancien ET nouveau range si les dates ou le
 * taux changent.
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

            // sync(): remplace intégralement le pivot · idempotent ·
            // supprime les véhicules absents de la liste, insère les
            // nouveaux. Liste vide = pivot purgé (= sémantique « tous »).
            $this->writer->syncVehicles($updated, $vehicleIds);

            return $updated->fresh(['vehicles', 'company:id,short_code,legal_name,color'])
                ?? throw new \RuntimeException('Failed to reload rental discount after update.');
        });
    }
}
