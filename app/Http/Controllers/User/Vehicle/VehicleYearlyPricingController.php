<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\DeleteVehicleYearlyPricingAction;
use App\Actions\Vehicle\UpsertVehicleYearlyPricingAction;
use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('create', VehicleYearlyPricing::class);

        $action->execute($vehicle->id, $data);

        return back()->with('toast-success', 'Tarif enregistré.');
    }

    public function destroy(
        Vehicle $vehicle,
        int $year,
        DeleteVehicleYearlyPricingAction $action,
    ): RedirectResponse {
        // Charge l'instance pour passer à la Policy (signature `delete(User,
        // VehicleYearlyPricing)`). Si le couple n'existe pas, l'Action lève
        // `VehicleYearlyPricingNotFoundException` ; ici on charge le pricing
        // d'abord pour un check d'autorisation précis avant l'effet.
        $pricing = VehicleYearlyPricing::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('year', $year)
            ->first();

        if ($pricing !== null) {
            Gate::authorize('delete', $pricing);
        } else {
            Gate::authorize('viewAny', VehicleYearlyPricing::class);
        }

        $action->execute($vehicle->id, $year);

        return back()->with('toast-success', 'Tarif supprimé.');
    }
}
