<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Data\User\Vehicle\VehicleListItemData;
use App\Data\User\Vehicle\VehicleOptionData;
use App\Models\Vehicle;
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Spatie\LaravelData\DataCollection;

/**
 * Requêtes lecture sur le domaine Vehicle.
 *
 * Tous les `Vehicle::query()` du projet vivent ici. Eager-loading
 * systématique des `fiscalCharacteristics` actives pour éviter tout
 * N+1 dans les agrégations fiscales.
 */
final class VehicleQueryService
{
    public function __construct(
        private readonly AssignmentQueryService $assignments,
        private readonly FleetFiscalAggregator $aggregator,
    ) {}

    /**
     * Liste des véhicules pour la page « Flotte » avec taxe annuelle
     * agrégée par véhicule (somme sur toutes les entreprises).
     *
     * @return DataCollection<int, VehicleListItemData>
     */
    public function listForFleetView(int $year): DataCollection
    {
        $cumul = $this->assignments->loadAnnualCumul($year);

        $vehicles = Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->orderByDesc('acquisition_date')
            ->get();

        $rows = $vehicles
            ->map(fn (Vehicle $v): VehicleListItemData => new VehicleListItemData(
                id: $v->id,
                licensePlate: $v->license_plate,
                brand: $v->brand,
                model: $v->model,
                currentStatus: $v->current_status,
                firstFrenchRegistrationDate: $v->first_french_registration_date->format('Y-m-d'),
                acquisitionDate: $v->acquisition_date->format('Y-m-d'),
                exitDate: $v->exit_date?->format('Y-m-d'),
                annualTaxDue: $this->aggregator->vehicleAnnualTax($v, $cumul, $year),
            ))
            ->values()
            ->all();

        return VehicleListItemData::collect($rows, DataCollection::class);
    }

    /**
     * Liste pour les `<SelectInput>` (Attribution rapide, etc.).
     * Filtre les véhicules sortis (`exit_date IS NOT NULL`).
     *
     * @return DataCollection<int, VehicleOptionData>
     */
    public function listForOptions(): DataCollection
    {
        $rows = Vehicle::query()
            ->whereNull('exit_date')
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'model'])
            ->map(static fn (Vehicle $v): VehicleOptionData => new VehicleOptionData(
                id: $v->id,
                licensePlate: $v->license_plate,
                label: sprintf('%s — %s %s', $v->license_plate, $v->brand, $v->model),
            ))
            ->values()
            ->all();

        return VehicleOptionData::collect($rows, DataCollection::class);
    }
}
