<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Exceptions\BaseAppException;

/**
 * L'édition d'une VFC produit des effets de bord destructifs (suppression
 * d'au moins une autre version de l'historique) que l'utilisateur n'a
 * pas explicitement confirmés.
 *
 * Le handler global (`bootstrap/app.php`) transforme cette exception
 * en flash `toast-warning` côté Inertia avec le détail des impacts —
 * l'utilisateur doit alors re-soumettre le formulaire en cochant la
 * case de confirmation pour appliquer la cascade.
 *
 * Le front mirroir (`computeVfcUpdateImpact.ts`) prévient ce cas en
 * affichant une modale de confirmation avant le submit, mais cette
 * exception reste le filet de sécurité backend.
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
