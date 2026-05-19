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
 * Per-year pricing CRUD scoped to a parent vehicle.
 */
final class VehicleYearlyPricingController extends Controller
{
    /**
     * Upsert the daily/weekly/monthly rates for the given vehicle × year.
     */
    public function store(
        Vehicle $vehicle,
        VehicleYearlyPricingData $data,
        UpsertVehicleYearlyPricingAction $action,
    ): RedirectResponse {
        Gate::authorize('create', VehicleYearlyPricing::class);

        $action->execute($vehicle->id, $data);

        return back()->with('toast-success', 'Tarif enregistré.');
    }

    /**
     * Delete a vehicle pricing for the given year.
     *
     * Loads the row first to authorise against the instance policy, with
     * a viewAny fallback when the row does not exist (the action then
     * raises VehicleYearlyPricingNotFoundException).
     */
    public function destroy(
        Vehicle $vehicle,
        int $year,
        DeleteVehicleYearlyPricingAction $action,
    ): RedirectResponse {
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
