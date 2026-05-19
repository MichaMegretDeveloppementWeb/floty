<?php

declare(strict_types=1);

namespace App\Exceptions\RentalDiscount;

use App\Exceptions\BaseAppException;

/**
 * Rental discount not found (missing id, or soft-deleted without `withTrashed`).
 */
final class RentalDiscountNotFoundException extends BaseAppException
{
    public static function byId(int $id): self
    {
        return new self(
            technicalMessage: "RentalDiscount with id {$id} not found.",
            userMessage: 'La réduction commerciale demandée est introuvable.',
        );
    }
}
