<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Vehicle;
use App\Services\Invoice\InvoiceDivergenceFlagger;

/**
 * Observer Vehicle (T6 / Phase 14.R) — flag divergence des factures
 * lorsqu'`exit_date` change.
 *
 * **Pourquoi seulement `exit_date`** : T5 a câblé le clip
 * `exit_date` dans `BillingCalculator` (cohérence ADR-0018, defense
 * in depth). Une modification d'`exit_date` après émission d'une
 * facture impactée changerait le `daysUsed` calculé → divergence.
 * Les autres champs (plate, brand, model, color, mileage, notes) ne
 * participent pas au calcul facturable, donc on évite de flagger
 * inutilement.
 *
 * **Création de véhicule** : pas de hook `created` car aucun contrat
 * n'existe encore — rien à flagger.
 *
 * **Branchement** : `#[ObservedBy([VehicleObserver::class])]` sur le
 * Model.
 */
final class VehicleObserver
{
    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
    ) {}

    public function updated(Vehicle $vehicle): void
    {
        if (! $vehicle->wasChanged('exit_date')) {
            return;
        }

        $this->flagger->flagForVehicle($vehicle->id);
    }
}
