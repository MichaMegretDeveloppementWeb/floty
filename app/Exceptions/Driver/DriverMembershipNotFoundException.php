<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Exceptions\BaseAppException;

/**
 * Operation attempted on a missing Driver-Company membership (leave without active membership, or detach by unknown pivot id).
 */
final class DriverMembershipNotFoundException extends BaseAppException
{
    public static function forActiveMembership(int $driverId, int $companyId): self
    {
        return new self(
            sprintf('No active membership for driver %d in company %d.', $driverId, $companyId),
            'Aucun rattachement actif à cette entreprise.',
        );
    }

    public static function forPivotId(int $pivotId): self
    {
        return new self(
            sprintf('Membership pivot %d not found.', $pivotId),
            'Rattachement introuvable.',
        );
    }
}
