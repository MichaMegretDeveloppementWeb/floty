<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\DeleteVehicleYearlyPricingAction;
use App\Actions\Vehicle\UpsertVehicleYearlyPricingAction;
use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD léger sur les tarifs jour/semaine/mois d'un véhicule par année.
 * Endpoints opérés depuis la page Show véhicule (chantier 14.B intégrera
 * un `PricingEditor` inline qui pilote ces routes).
 *
 * Pas d'Index / Show dédié : le tarif vit dans le contexte de son véhicule
 * parent et son cycle de vie est géré directement depuis la fiche
 * véhicule.
 */
final class VehicleYearlyPricingController extends Controller
{
    public function store(
        Vehicle $vehicle,
        VehicleYearlyPricingData $data,
        UpsertVehicleYearlyPricingAction $action,
    ): RedirectResponse {
        $action->execute($vehicle->id, $data);

        return back()->with('toast-success', 'Tarif enregistré.');
    }

    public function destroy(
        Vehicle $vehicle,
        int $year,
        DeleteVehicleYearlyPricingAction $action,
    ): RedirectResponse {
        $action->execute($vehicle->id, $year);

        return back()->with('toast-success', 'Tarif supprimé.');
    }
}
