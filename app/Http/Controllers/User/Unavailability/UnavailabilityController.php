<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Unavailability;

use App\Actions\Unavailability\CreateUnavailabilityAction;
use App\Actions\Unavailability\DeleteUnavailabilityAction;
use App\Actions\Unavailability\UpdateUnavailabilityAction;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Data\User\Unavailability\UpdateUnavailabilityData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class UnavailabilityController extends Controller
{
    public function store(
        StoreUnavailabilityData $data,
        CreateUnavailabilityAction $action,
    ): RedirectResponse {
        $unavailability = $action->execute($data);

        return back()->with('toast-success', 'Indisponibilité ajoutée.');
    }

    public function update(
        int $unavailability,
        UpdateUnavailabilityData $data,
        UpdateUnavailabilityAction $action,
    ): RedirectResponse {
        $action->execute($unavailability, $data);

        return back()->with('toast-success', 'Indisponibilité modifiée.');
    }

    public function destroy(
        int $unavailability,
        DeleteUnavailabilityAction $action,
    ): RedirectResponse {
        $action->execute($unavailability);

        return back()->with('toast-success', 'Indisponibilité supprimée.');
    }
}
