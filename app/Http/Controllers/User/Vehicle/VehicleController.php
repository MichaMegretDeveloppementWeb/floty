<?php

namespace App\Http\Controllers\User\Vehicle;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Vehicle\StoreVehicleRequest;
use App\Models\Assignment;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(private readonly FiscalCalculator $calculator) {}

    public function index(): Response
    {
        $year = 2024;

        $cumulByPair = [];
        Assignment::query()
            ->whereYear('date', $year)
            ->get(['vehicle_id', 'company_id'])
            ->each(function ($a) use (&$cumulByPair): void {
                $key = $a->vehicle_id.'|'.$a->company_id;
                $cumulByPair[$key] = ($cumulByPair[$key] ?? 0) + 1;
            });

        $vehicles = Vehicle::query()
            ->orderByDesc('acquisition_date')
            ->get();

        $rows = $vehicles->map(function (Vehicle $v) use ($cumulByPair, $year): array {
            $total = 0.0;
            foreach ($cumulByPair as $key => $days) {
                [$vehicleId] = explode('|', (string) $key);
                if ((int) $vehicleId !== $v->id) {
                    continue;
                }
                $breakdown = $this->calculator->calculate($v, $days, $days, $year);
                $total += $breakdown->totalDue;
            }

            return [
                'id' => $v->id,
                'licensePlate' => $v->license_plate,
                'brand' => $v->brand,
                'model' => $v->model,
                'currentStatus' => $v->current_status->value,
                'firstFrenchRegistrationDate' => $v->first_french_registration_date->format('Y-m-d'),
                'acquisitionDate' => $v->acquisition_date->format('Y-m-d'),
                'exitDate' => $v->exit_date?->format('Y-m-d'),
                'annualTaxDue' => round($total, 2),
            ];
        });

        return Inertia::render('User/Vehicles/Index', [
            'vehicles' => $rows,
            'fiscalYear' => $year,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Vehicles/Create', [
            'enums' => [
                'receptionCategories' => $this->enumOptions(ReceptionCategory::cases()),
                'vehicleUserTypes' => $this->enumOptions(VehicleUserType::cases()),
                'bodyTypes' => $this->enumOptions(BodyType::cases()),
                'energySources' => $this->enumOptions(EnergySource::cases()),
                'euroStandards' => $this->enumOptions(EuroStandard::cases()),
                'homologationMethods' => $this->enumOptions(HomologationMethod::cases()),
                'pollutantCategories' => $this->enumOptions(PollutantCategory::cases()),
            ],
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $vehicle = Vehicle::create([
                'license_plate' => mb_strtoupper($data['license_plate']),
                'brand' => $data['brand'],
                'model' => $data['model'],
                'vin' => $data['vin'] ?? null,
                'color' => $data['color'] ?? null,
                'first_french_registration_date' => $data['first_french_registration_date'],
                'first_origin_registration_date' => $data['first_origin_registration_date'],
                'first_economic_use_date' => $data['first_economic_use_date'],
                'acquisition_date' => $data['acquisition_date'],
                'current_status' => VehicleStatus::Active,
                'mileage_current' => $data['mileage_current'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            VehicleFiscalCharacteristics::create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => $vehicle->acquisition_date,
                'effective_to' => null,
                'reception_category' => $data['reception_category'],
                'vehicle_user_type' => $data['vehicle_user_type'],
                'body_type' => $data['body_type'],
                'seats_count' => $data['seats_count'],
                'energy_source' => $data['energy_source'],
                'euro_standard' => $data['euro_standard'] ?? null,
                'pollutant_category' => $data['pollutant_category'],
                'homologation_method' => $data['homologation_method'],
                'co2_wltp' => $data['co2_wltp'] ?? null,
                'co2_nedc' => $data['co2_nedc'] ?? null,
                'taxable_horsepower' => $data['taxable_horsepower'] ?? null,
                'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
            ]);
        });

        return redirect()
            ->route('user.vehicles.index')
            ->with('toast-success', 'Véhicule enregistré.');
    }

    /**
     * Convertit une liste de cases d'enum en options de select. Chaque
     * enum métier expose une méthode `label()` qui retourne le libellé
     * FR affichable — la valeur brute reste envoyée côté serveur.
     *
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            static fn (\BackedEnum $case): array => [
                'value' => $case->value,
                'label' => method_exists($case, 'label')
                    ? $case->label()
                    : $case->value,
            ],
            $cases,
        );
    }
}
