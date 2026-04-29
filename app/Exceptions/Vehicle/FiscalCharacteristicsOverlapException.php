<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * La modification des bornes (`effective_from`/`effective_to`) d'une
 * VFC produirait un chevauchement avec une autre version existante
 * de l'historique du même véhicule.
 *
 * L'UI bloque normalement en amont (validation côté formulaire), cette
 * exception est le filet de sécurité backend.
 */
final class FiscalCharacteristicsOverlapException extends BaseAppException
{
    public static function withVersion(string $effectiveFrom, ?string $effectiveTo): self
    {
        $period = $effectiveTo === null
            ? "depuis le {$effectiveFrom}"
            : "du {$effectiveFrom} au {$effectiveTo}";

        return new self(
            technicalMessage: "Fiscal characteristics bounds overlap with version {$period}.",
            userMessage: "Les nouvelles bornes chevauchent une autre version de l'historique fiscal ({$period}). Ajustez les dates pour éviter le chevauchement.",
        );
    }
}
