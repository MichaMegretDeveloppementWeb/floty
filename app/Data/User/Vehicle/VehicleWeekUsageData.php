<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail d'une semaine ISO de l'année dans la timeline d'utilisation
 * d'un véhicule. Contient la liste des entreprises ayant utilisé le
 * véhicule sur cette semaine, avec leur nombre de jours respectif.
 *
 * Les semaines sans aucune attribution sont exposées avec
 * `segments = []` et `totalDays = 0` — le front rend alors une
 * cellule vide (pas de filtre côté consommateur).
 */
#[TypeScript]
final class VehicleWeekUsageData extends Data
{
    /**
     * @param  list<VehicleWeekSegmentData>  $segments
     * @param  int  $unavailabilityDays  Nombre de jours d'indispo
     *                                   (tous types) recoupant cette
     *                                   semaine ISO de l'année. Sert
     *                                   à dimensionner un segment
     *                                   « indispo » empilé au-dessus
     *                                   des attributions dans la
     *                                   timeline (proportionnel sur 7).
     */
    public function __construct(
        public int $weekNumber,
        #[DataCollectionOf(VehicleWeekSegmentData::class)]
        public array $segments,
        public int $totalDays,
        public int $unavailabilityDays,
    ) {}
}
