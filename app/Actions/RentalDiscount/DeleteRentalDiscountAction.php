<?php

declare(strict_types=1);

namespace App\Actions\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Models\RentalDiscount;

/**
 * Soft-delete une réduction commerciale (Lot 4 du chantier
 * RentalDiscount).
 *
 * **Doctrine** ·
 *   - Les factures déjà émises restent intactes (`applied_discount_id`
 *     FK nullOnDelete · le soft-delete préserve la FK car ne supprime
 *     pas la row).
 *   - Les snapshots `applied_discount_label_snapshot` /
 *     `applied_discount_basis_points_snapshot` (Lot 3) garantissent
 *     l'affichage historique même si la FK bascule un jour à null
 *     (cas exceptionnel hard-delete admin).
 *   - L'observer `RentalDiscountObserver` (Lot 3) flippe `is_divergent`
 *     sur les factures de la période · l'utilisateur saura quoi
 *     régénérer.
 */
final readonly class DeleteRentalDiscountAction
{
    public function __construct(
        private RentalDiscountWriteRepositoryInterface $writer,
    ) {}

    public function execute(RentalDiscount $discount): void
    {
        $this->writer->softDelete($discount);
    }
}
