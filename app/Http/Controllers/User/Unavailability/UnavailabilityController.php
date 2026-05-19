<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Unavailability;

use App\Actions\Unavailability\CreateUnavailabilityAction;
use App\Actions\Unavailability\DeleteUnavailabilityAction;
use App\Actions\Unavailability\UpdateUnavailabilityAction;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Data\User\Unavailability\UpdateUnavailabilityData;
use App\Http\Controllers\Controller;
use App\Models\Unavailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class UnavailabilityController extends Controller
{
    /**
     * Create a new unavailability.
     */
    public function store(
        StoreUnavailabilityData $data,
        CreateUnavailabilityAction $action,
    ): RedirectResponse {
        Gate::authorize('create', Unavailability::class);

        $unavailability = $action->execute($data);

        return back()->with('toast-success', 'Indisponibilité ajoutée.');
    }

    /**
     * Update an existing unavailability.
     */
    public function update(
        int $unavailability,
        UpdateUnavailabilityData $data,
        UpdateUnavailabilityAction $action,
    ): RedirectResponse {
        $model = Unavailability::query()->findOrFail($unavailability);
        Gate::authorize('update', $model);

        $action->execute($unavailability, $data);

        return back()->with('toast-success', 'Indisponibilité modifiée.');
    }

    /**
     * Delete an unavailability.
     */
    public function destroy(
        int $unavailability,
        DeleteUnavailabilityAction $action,
    ): RedirectResponse {
        $model = Unavailability::query()->findOrFail($unavailability);
        Gate::authorize('delete', $model);

        $action->execute($unavailability);

        return back()->with('toast-success', 'Indisponibilité supprimée.');
    }
}
