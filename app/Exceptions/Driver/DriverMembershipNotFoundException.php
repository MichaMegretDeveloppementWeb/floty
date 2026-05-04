<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Exceptions\BaseAppException;

/**
 * Levée quand on tente d'opérer sur une membership Driver↔Company
 * inexistante : leave d'une company sans membership active, ou détach
 * d'un pivot id introuvable. Remplace l'ancien `return` silencieux qui
 * faisait afficher un toast de succès trompeur côté UI.
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
