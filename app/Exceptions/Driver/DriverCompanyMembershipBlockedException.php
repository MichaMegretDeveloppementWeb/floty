<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Actions\Driver\LeaveDriverCompanyMembershipAction;
use App\Exceptions\BaseAppException;

/**
 * Driver-Company membership detach blocked because at least one contract references it.
 * Use {@see LeaveDriverCompanyMembershipAction} to set `left_at` while preserving history.
 */
final class DriverCompanyMembershipBlockedException extends BaseAppException
{
    public static function hasContracts(int $pivotId, int $contractsCount): self
    {
        return new self(
            sprintf('Membership pivot %d cannot be detached: %d contracts associated.', $pivotId, $contractsCount),
            sprintf(
                'Impossible de détacher ce conducteur de cette entreprise : '
                .'%d location(s) y sont liées. Pour le sortir tout en conservant '
                .'l\'historique, utilisez l\'action « Sortir » qui pose une date de sortie.',
                $contractsCount,
            ),
        );
    }
}
