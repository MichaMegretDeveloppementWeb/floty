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
 * CRUD complet sur l'historique fiscal d'un véhicule (modale
 * Historique de la page Show). `store()` permet d'ajouter une nouvelle
 * version VFC à n'importe quelle position de l'historique (avant la 1ʳᵉ,
 * entre 2 existantes, comme nouvelle courante…) ; les versions adjacentes
 * sont ajustées ou supprimées en cascade par
 * {@see CreateFiscalCharacteristicsAction}. Cohabite avec le « mode
 * Nouvelle version » du formulaire d'édition véhicule (qui crée
 * également une VFC mais via `UpdateVehicleAction`, pour cohérence
 * avec une mise à jour combinée identité + fiscalité).
 */
final class VehicleFiscalCharacteristicsController extends Controller
{
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
