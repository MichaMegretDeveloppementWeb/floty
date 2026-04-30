<?php

declare(strict_types=1);

namespace App\Exceptions\Unavailability;

use App\Exceptions\BaseAppException;
use Illuminate\Support\Carbon;

/**
 * Une indisponibilité est créée ou modifiée sur une plage qui chevauche
 * au moins un contrat actif du véhicule.
 *
 * L'UI bloque déjà cette saisie via `disabledDates` mais cette exception
 * sert de filet de sécurité au niveau Action — couvre le cas d'un POST
 * direct hors UI ou d'un contrat créé par un autre user pendant que le
 * formulaire d'indispo était ouvert.
 *
 * Le message utilisateur liste les dates en conflit au format français
 * pour permettre une correction rapide côté UI.
 */
final class UnavailabilityOverlapsContractsException extends BaseAppException
{
    /**
     * @param  list<string>  $conflictingDates  Dates ISO Y-m-d en conflit
     */
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly array $conflictingDates,
    ) {
        parent::__construct($technicalMessage, $userMessage);
    }

    /**
     * @param  list<string>  $dates  Dates ISO Y-m-d en conflit avec
     *                               la plage d'indispo demandée
     */
    public static function withConflicts(array $dates): self
    {
        $formatted = array_map(
            static fn (string $iso): string => Carbon::parse($iso)->format('d/m/Y'),
            $dates,
        );

        return new self(
            technicalMessage: sprintf(
                'Unavailability range overlaps %d existing contract day(s): %s',
                count($dates),
                implode(', ', $dates),
            ),
            userMessage: sprintf(
                'Période en conflit avec des contrats existants : %s. '
                .'Retirez d\'abord ces contrats ou ajustez la plage d\'indisponibilité.',
                implode(', ', $formatted),
            ),
            conflictingDates: $dates,
        );
    }
}
