<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureWriteRepositoryInterface;
use App\Data\User\VehicleEvent\StoreVehicleEventNatureSuggestionData;
use App\Http\Controllers\Controller;
use App\Models\VehicleEvent;
use App\Models\VehicleEventNature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class VehicleEventNatureController extends Controller
{
    public function __construct(
        private readonly VehicleEventNatureWriteRepositoryInterface $natures,
    ) {}

    /**
     * « Ajouter à la liste » (event form): persists a free nature as a future
     * non-reductive suggestion of the catalogue. Direct repository call: a
     * single idempotent insert, no orchestration to delegate. The form
     * partial-reloads its `natureSuggestions` prop on success.
     */
    public function store(StoreVehicleEventNatureSuggestionData $data): RedirectResponse
    {
        Gate::authorize('create', VehicleEvent::class);

        $this->natures->addNonReductiveSuggestion($data->label);

        return back()->with('toast-success', 'Nature ajoutée aux suggestions.');
    }

    /**
     * Removes a non-reductive suggestion from the catalogue (the « x » of the
     * suggestion list). Only the frozen reductive block is protected; events
     * keep their attached natures untouched.
     */
    public function destroy(VehicleEventNature $vehicleEventNature): RedirectResponse
    {
        Gate::authorize('create', VehicleEvent::class);

        if (! $this->natures->deleteSuggestion($vehicleEventNature)) {
            return back()->with('toast-error', 'Les natures fiscalement réductrices sont obligatoires et ne peuvent pas être retirées.');
        }

        return back()->with('toast-success', 'Nature retirée des suggestions.');
    }
}
