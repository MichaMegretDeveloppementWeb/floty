<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureWriteRepositoryInterface;
use App\Data\User\VehicleEvent\StoreVehicleEventNatureSuggestionData;
use App\Http\Controllers\Controller;
use App\Models\VehicleEvent;
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
}
