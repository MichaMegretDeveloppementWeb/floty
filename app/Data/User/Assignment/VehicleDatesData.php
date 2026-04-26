<?php

namespace App\Data\User\Assignment;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Réponse de l'endpoint `GET /app/assignments/vehicle-dates` —
 * dates occupées d'un véhicule (toutes entreprises) + map des dates
 * actuelles par entreprise (pour l'affichage du calendrier
 * d'attribution).
 *
 * `pairDates` : clés = `(string) $companyId`, valeurs = liste de dates ISO.
 */
#[TypeScript]
final class VehicleDatesData extends Data
{
    /**
     * @param  list<string>  $vehicleBusyDates
     * @param  array<string, list<string>>  $pairDates
     */
    public function __construct(
        public array $vehicleBusyDates,
        public array $pairDates,
    ) {}
}
