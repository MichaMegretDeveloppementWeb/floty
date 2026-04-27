<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Vehicle\VehicleListItemData;
use App\Data\User\Vehicle\VehicleOptionData;
use App\Models\Vehicle;
use App\Services\Fiscal\FleetFiscalAggregator;
use Spatie\LaravelData\DataCollection;

/**
 * Orchestration des lectures du domaine Vehicle vers les DTOs exposés.
 *
 * Aucune query Eloquent ici — toutes les lectures passent par les
 * repositories. Le service combine repository + aggregator fiscal +
 * mapping DTO (R3 d'ADR-0013).
 */
final class VehicleQueryService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly AssignmentReadRepositoryInterface $assignments,
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

        $rows = $this->vehicles->findAllForFleetView()
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
        $rows = $this->vehicles->findAllForOptions()
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
