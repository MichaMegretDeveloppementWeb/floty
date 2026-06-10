<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventDetailSuggestionWriteRepositoryInterface;
use App\Data\User\VehicleEvent\StoreVehicleEventDetailSuggestionData;
use App\Http\Controllers\Controller;
use App\Models\VehicleEvent;
use App\Models\VehicleEventDetailSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class VehicleEventDetailSuggestionController extends Controller
{
    public function __construct(
        private readonly VehicleEventDetailSuggestionWriteRepositoryInterface $suggestions,
    ) {}

    /**
     * « Ajouter à la liste » (section « Détails » of the event form): persists
     * a free detail line as a future suggestion. Direct repository call: a
     * single idempotent insert, no orchestration to delegate. The form
     * partial-reloads its `detailSuggestions` prop on success.
     */
    public function store(StoreVehicleEventDetailSuggestionData $data): RedirectResponse
    {
        Gate::authorize('create', VehicleEvent::class);

        $this->suggestions->addSuggestion($data->label);

        return back()->with('toast-success', 'Détail ajouté aux suggestions.');
    }

    /**
     * Removes a detail suggestion (the « x » of the suggestion list). The
     * whole catalogue is user-managed; events keep their attached detail
     * lines untouched.
     */
    public function destroy(VehicleEventDetailSuggestion $vehicleEventDetailSuggestion): RedirectResponse
    {
        Gate::authorize('create', VehicleEvent::class);

        $this->suggestions->deleteSuggestion($vehicleEventDetailSuggestion);

        return back()->with('toast-success', 'Détail retiré des suggestions.');
    }
}
