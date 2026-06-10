<?php

declare(strict_types=1);

namespace App\Support\VehicleEvent;

/**
 * Canonical base catalogue of event natures (refonte type → nature), mirrored
 * into `vehicle_event_natures` by VehicleEventNatureSeeder.
 *
 * REDUCTIVE is the frozen fiscally-reductive block (the three off-road cases
 * of BOFiP BOI-AIS-MOB-10-30-10 § 50 / § 60). Any other nature, including
 * free user entries, is non-reductive. Extending REDUCTIVE means updating
 * this list then re-running the seeder (updateOrCreate, no duplicate on
 * re-seed).
 */
final class EventNatureCatalog
{
    /** @var list<string> */
    public const array REDUCTIVE = [
        'Sinistre avec interdiction de circuler',
        'Fourrière (demande publique)',
        "Suspension du certificat d'immatriculation",
    ];

    /** @var list<string> */
    public const array NON_REDUCTIVE = [
        'Maintenance préventive',
        'Maintenance corrective',
        'Entretien',
        'Sinistre',
        'Vol',
        'Contrôle réglementaire',
        'Cycle de vie',
        'Administratif',
    ];
}
