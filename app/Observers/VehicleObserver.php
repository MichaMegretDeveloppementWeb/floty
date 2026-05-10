<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Vehicle;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Support\Facades\Auth;

/**
 * Observer Vehicle (T6 / Phase 14.R + audit pré-livraison D5.7.8) ·
 * deux responsabilités sur changement d'`exit_date` :
 *
 *   1. Flag divergence des factures via {@see InvoiceDivergenceFlagger}
 *      (T5 ADR-0018) : clip d'`exit_date` dans `BillingCalculator`.
 *
 *   2. Invalide les déclarations fiscales actives via
 *      {@see DeclarationInvalidationDetector::flagForVehicle} (audit
 *      D5.7.8) : `exit_date` clôture les contrats au-delà de la date,
 *      donc le périmètre taxable change. Sans cette invalidation, une
 *      déclaration générée affichait des montants divergents du
 *      périmètre réel sans signalement à l'utilisateur. Risque légal.
 *
 * **Pourquoi seulement `exit_date`** : les autres champs (plate,
 * brand, model, color, mileage, notes, vin) ne participent ni au
 * calcul facturable ni au calcul fiscal. On évite de flagger
 * inutilement.
 *
 * **Création de véhicule** : pas de hook `created` car aucun contrat
 * ni déclaration n'existe encore. Rien à flagger.
 *
 * **Branchement** : `#[ObservedBy([VehicleObserver::class])]` sur le
 * Model.
 */
final class VehicleObserver
{
    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
        private readonly DeclarationInvalidationDetector $declarationInvalidator,
    ) {}

    public function updated(Vehicle $vehicle): void
    {
        if (! $vehicle->wasChanged('exit_date')) {
            return;
        }

        $this->flagger->flagForVehicle($vehicle->id);

        $this->declarationInvalidator->flagForVehicle(
            vehicle: $vehicle,
            type: InvalidationReasonType::VehicleUpdated,
            actorUserId: Auth::id() ?? 0,
            fieldsChanged: ['exit_date'],
        );
    }
}
