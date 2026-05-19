<?php

declare(strict_types=1);

namespace App\Actions\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Models\RentalDiscount;

/**
 * Soft-deletes a rental discount.
 *
 * Already-issued invoices stay intact (`applied_discount_id` FK
 * nullOnDelete; soft-delete preserves the FK). The
 * `applied_discount_*_snapshot` columns guarantee accurate historical
 * display even if the FK is later nulled by a hard delete. The
 * `RentalDiscountObserver` flips `is_divergent` on the impacted
 * invoices so the user knows what to regenerate.
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
