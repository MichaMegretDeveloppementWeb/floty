<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\CreateFiscalCharacteristicsAction;
use App\Actions\Vehicle\DeleteFiscalCharacteristicsAction;
use App\Actions\Vehicle\UpdateFiscalCharacteristicsAction;
use App\Data\User\Vehicle\DeleteFiscalCharacteristicsData;
use App\Data\User\Vehicle\StoreFiscalCharacteristicsData;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Full CRUD over the fiscal history of a vehicle.
 *
 * Coexists with the "new version" mode of the vehicle edit form which
 * also creates a VFC but through UpdateVehicleAction for combined
 * identity + fiscal updates. Adjacent versions are auto-adjusted by
 * {@see CreateFiscalCharacteristicsAction}.
 */
final class VehicleFiscalCharacteristicsController extends Controller
{
    /**
     * Insert a new VFC version at any position in the history.
     */
    public function store(
        Vehicle $vehicle,
        StoreFiscalCharacteristicsData $data,
        CreateFiscalCharacteristicsAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $vehicle);

        $action->execute($vehicle, $data);

        $impactSummary = $this->summarizeImpacts($action->lastImpacts());

        $response = back()->with('toast-success', 'Nouvelle version fiscale ajoutée.');

        if ($impactSummary !== null) {
            $response = $response->with('toast-info', $impactSummary);
        }

        return $response;
    }

    /**
     * Update an existing VFC version.
     */
    public function update(
        int $vehicleFiscalCharacteristic,
        UpdateFiscalCharacteristicsData $data,
        UpdateFiscalCharacteristicsAction $action,
    ): RedirectResponse {
        $vfc = VehicleFiscalCharacteristics::query()->findOrFail($vehicleFiscalCharacteristic);
        Gate::authorize('update', $vfc);

        $action->execute($vehicleFiscalCharacteristic, $data);

        $impactSummary = $this->summarizeImpacts($action->lastImpacts());

        $response = back()->with('toast-success', 'Version fiscale mise à jour.');

        if ($impactSummary !== null) {
            $response = $response->with('toast-info', $impactSummary);
        }

        return $response;
    }

    /**
     * Delete a VFC version, extending neighbours per the chosen strategy.
     */
    public function destroy(
        int $vehicleFiscalCharacteristic,
        DeleteFiscalCharacteristicsData $data,
        DeleteFiscalCharacteristicsAction $action,
    ): RedirectResponse {
        $vfc = VehicleFiscalCharacteristics::query()->findOrFail($vehicleFiscalCharacteristic);
        Gate::authorize('delete', $vfc);

        $action->execute(
            $vehicleFiscalCharacteristic,
            $data->extensionStrategy,
        );

        return back()->with('toast-success', 'Version fiscale supprimée.');
    }

    /**
     * Format an info toast describing cascade adjustments on adjacent versions.
     *
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    private function summarizeImpacts(array $impacts): ?string
    {
        if ($impacts === []) {
            return null;
        }

        $lines = array_map(
            static fn (FiscalCharacteristicsImpact $i): string => '- '.$i->describe(),
            $impacts,
        );

        $count = count($impacts);

        return sprintf(
            "%s sur les versions adjacentes :\n%s",
            $count === 1 ? 'Ajustement automatique appliqué' : "{$count} ajustements automatiques appliqués",
            implode("\n", $lines),
        );
    }
}
