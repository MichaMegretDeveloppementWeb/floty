<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Exceptions\BaseAppException;

/**
 * VFC edit has destructive side effects (deleting one or more historic versions) that the user has not confirmed.
 * Backend safety net; the frontend confirmation modal (`computeVfcUpdateImpact.ts`) catches this in advance.
 */
final class FiscalCharacteristicsRequiresConfirmationException extends BaseAppException
{
    /**
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    public static function withImpacts(array $impacts): self
    {
        $deletions = array_values(array_filter(
            $impacts,
            static fn (FiscalCharacteristicsImpact $i): bool => $i->isDestructive(),
        ));

        $count = count($deletions);
        $lines = array_map(
            static fn (FiscalCharacteristicsImpact $i): string => '· '.$i->describe(),
            $deletions,
        );

        $userMessage = sprintf(
            "Cette modification %s. Confirmez l'opération pour appliquer la cascade.\n%s",
            $count === 1
                ? 'supprimera 1 autre version de l\'historique'
                : sprintf('supprimera %d autres versions de l\'historique', $count),
            implode("\n", $lines),
        );

        return new self(
            technicalMessage: sprintf(
                'Fiscal characteristics update requires confirmation: %d destructive impact(s).',
                $count,
            ),
            userMessage: $userMessage,
        );
    }
}
