<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VehicleEvent;

use App\Actions\VehicleEvent\CreateVehicleEventAction;
use App\Actions\VehicleEvent\DeleteVehicleEventAction;
use App\Actions\VehicleEvent\UpdateVehicleEventAction;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Data\User\VehicleEvent\UpdateVehicleEventData;
use App\Http\Controllers\Controller;
use App\Models\VehicleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class VehicleEventController extends Controller
{
    /**
     * Create a new unavailability.
     */
    public function store(
        StoreVehicleEventData $data,
        CreateVehicleEventAction $action,
    ): RedirectResponse {
        Gate::authorize('create', VehicleEvent::class);

        $vehicleEvent = $action->execute($data);

        return back()->with('toast-success', 'Indisponibilité ajoutée.');
    }

    /**
     * Update an existing unavailability.
     */
    public function update(
        int $vehicleEvent,
        UpdateVehicleEventData $data,
        UpdateVehicleEventAction $action,
    ): RedirectResponse {
        $model = VehicleEvent::query()->findOrFail($vehicleEvent);
        Gate::authorize('update', $model);

        $action->execute($vehicleEvent, $data);

        return back()->with('toast-success', 'Indisponibilité modifiée.');
    }

    /**
     * Delete an unavailability.
     */
    public function destroy(
        int $vehicleEvent,
        DeleteVehicleEventAction $action,
    ): RedirectResponse {
        $model = VehicleEvent::query()->findOrFail($vehicleEvent);
        Gate::authorize('delete', $model);

        $action->execute($vehicleEvent);

        return back()->with('toast-success', 'Indisponibilité supprimée.');
    }
}
