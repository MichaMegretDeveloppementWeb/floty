<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Actions\Driver\LeaveDriverCompanyMembershipAction;
use App\Exceptions\BaseAppException;

/**
 * Suppression d'une membership Driver↔Company refusée car elle a
 * au moins un contrat associé (le détachement ne doit être possible
 * que si aucun contrat n'est rattaché à cette company pour ce driver).
 *
 * Pour sortir un driver d'une entreprise tout en conservant l'historique,
 * utiliser plutôt {@see LeaveDriverCompanyMembershipAction}
 * qui pose `left_at` sur la pivot.
 */
final class DriverCompanyMembershipBlockedException extends BaseAppException
{
    public static function hasContracts(int $pivotId, int $contractsCount): self
    {
        return new self(
            sprintf('Membership pivot %d cannot be detached: %d contracts associated.', $pivotId, $contractsCount),
            sprintf(
                'Impossible de détacher ce conducteur de cette entreprise : '
                .'%d contrat(s) y sont rattachés. Pour le sortir tout en conservant '
                .'l\'historique, utilisez l\'action « Sortir » qui pose une date de sortie.',
                $contractsCount,
            ),
        );
    }
}
