<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\DeleteFiscalCharacteristicsAction;
use App\Actions\Vehicle\UpdateFiscalCharacteristicsAction;
use App\Data\User\Vehicle\DeleteFiscalCharacteristicsData;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD partiel sur l'historique fiscal d'un véhicule (modale
 * Historique de la page Show). Le store est porté par
 * `UpdateVehicleAction` (mode « Nouvelle version » du formulaire
 * d'édition véhicule), pas ici - ce controller ne gère que
 * l'update et le delete d'une VFC existante.
 */
final class VehicleFiscalCharacteristicsController extends Controller
{
    public function update(
        int $vehicleFiscalCharacteristic,
        UpdateFiscalCharacteristicsData $data,
        UpdateFiscalCharacteristicsAction $action,
    ): RedirectResponse {
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
