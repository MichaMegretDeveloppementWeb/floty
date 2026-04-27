<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Planning\PlanningHeatmapVehicleData;
use App\Models\Company;
use App\Models\Vehicle;
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Spatie\LaravelData\DataCollection;

/**
 * Construction de la matrice véhicules × 52 semaines pour la page
 * « Vue d'ensemble » (planning).
 *
 * Une seule charge `Vehicle` (avec eager-load `fiscalCharacteristics`
 * actives) + une seule charge `Company` actives. Aucun N+1.
 */
final class PlanningHeatmapService
{
    public function __construct(
        private readonly AssignmentQueryService $assignments,
        private readonly FleetFiscalAggregator $aggregator,
    ) {}

    /**
     * @return array{vehicles: DataCollection<int, PlanningHeatmapVehicleData>, companies: DataCollection<int, CompanyOptionData>}
     */
    public function buildHeatmap(int $year): array
    {
        $cumul = $this->assignments->loadAnnualCumul($year);
        $weekDensity = $this->assignments->loadWeekDensity($year);

        $vehicles = Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->whereNull('deleted_at')
            ->orderBy('license_plate')
            ->get();

        $vehicleRows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeks = [];
            for ($w = 1; $w <= 52; $w++) {
                $weeks[] = $weekDensity[$vehicle->id.'|'.$w] ?? 0;
            }

            $vehicleRows[] = new PlanningHeatmapVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeks: $weeks,
                daysTotal: array_sum($weeks),
                annualTaxDue: $this->aggregator->vehicleAnnualTax($vehicle, $cumul, $year),
            );
        }

        $companyRows = Company::query()
            ->where('is_active', true)
            ->orderBy('short_code')
            ->get(['id', 'short_code', 'legal_name', 'color'])
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return [
            'vehicles' => PlanningHeatmapVehicleData::collect($vehicleRows, DataCollection::class),
            'companies' => CompanyOptionData::collect($companyRows, DataCollection::class),
        ];
    }
}
