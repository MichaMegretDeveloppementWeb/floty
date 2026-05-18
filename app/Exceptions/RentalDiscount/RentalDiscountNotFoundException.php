<?php

declare(strict_types=1);

namespace App\Exceptions\RentalDiscount;

use App\Exceptions\BaseAppException;

/**
 * Levée quand on tente d'accéder à une réduction commerciale qui
 * n'existe pas ou a été soft-deletée et n'est pas demandée en mode
 * `withTrashed`.
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
