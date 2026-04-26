<?php

namespace App\Http\Controllers\User\Vehicle;

use App\Data\User\Vehicle\EnumOptionData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\VehicleFormOptionsData;
use App\Data\User\Vehicle\VehicleListItemData;
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
use App\Models\Assignment;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

final class VehicleController extends Controller
{
    public function __construct(private readonly FiscalCalculator $calculator) {}

    public function index(): Response
    {
        $year = (int) config('floty.fiscal.current_year');

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

        $rows = $vehicles->map(function (Vehicle $v) use ($cumulByPair, $year): VehicleListItemData {
            $total = 0.0;
            foreach ($cumulByPair as $key => $days) {
                [$vehicleId] = explode('|', (string) $key);
                if ((int) $vehicleId !== $v->id) {
                    continue;
                }
                $breakdown = $this->calculator->calculate($v, $days, $days, $year);
                $total += $breakdown->totalDue;
            }

            return new VehicleListItemData(
                id: $v->id,
                licensePlate: $v->license_plate,
                brand: $v->brand,
                model: $v->model,
                currentStatus: $v->current_status,
                firstFrenchRegistrationDate: $v->first_french_registration_date->format('Y-m-d'),
                acquisitionDate: $v->acquisition_date->format('Y-m-d'),
                exitDate: $v->exit_date?->format('Y-m-d'),
                annualTaxDue: round($total, 2),
            );
        })->values()->all();

        return Inertia::render('User/Vehicles/Index', [
            'vehicles' => VehicleListItemData::collect($rows, DataCollection::class),
        ]);
    }

    public function create(): Response
    {
        $options = new VehicleFormOptionsData(
            receptionCategories: $this->enumOptions(ReceptionCategory::cases()),
            vehicleUserTypes: $this->enumOptions(VehicleUserType::cases()),
            bodyTypes: $this->enumOptions(BodyType::cases()),
            energySources: $this->enumOptions(EnergySource::cases()),
            euroStandards: $this->enumOptions(EuroStandard::cases()),
            homologationMethods: $this->enumOptions(HomologationMethod::cases()),
            pollutantCategories: $this->enumOptions(PollutantCategory::cases()),
        );

        return Inertia::render('User/Vehicles/Create', [
            'options' => $options,
        ]);
    }

    public function store(StoreVehicleData $data): RedirectResponse
    {
        DB::transaction(function () use ($data): void {
            $vehicle = Vehicle::create([
                'license_plate' => mb_strtoupper($data->licensePlate),
                'brand' => $data->brand,
                'model' => $data->model,
                'vin' => $data->vin,
                'color' => $data->color,
                'first_french_registration_date' => $data->firstFrenchRegistrationDate,
                'first_origin_registration_date' => $data->firstOriginRegistrationDate,
                'first_economic_use_date' => $data->firstEconomicUseDate,
                'acquisition_date' => $data->acquisitionDate,
                'current_status' => VehicleStatus::Active,
                'mileage_current' => $data->mileageCurrent,
                'notes' => $data->notes,
            ]);

            VehicleFiscalCharacteristics::create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => $vehicle->acquisition_date,
                'effective_to' => null,
                'reception_category' => $data->receptionCategory,
                'vehicle_user_type' => $data->vehicleUserType,
                'body_type' => $data->bodyType,
                'seats_count' => $data->seatsCount,
                'energy_source' => $data->energySource,
                'euro_standard' => $data->euroStandard,
                'pollutant_category' => $data->pollutantCategory,
                'homologation_method' => $data->homologationMethod,
                'co2_wltp' => $data->co2Wltp,
                'co2_nedc' => $data->co2Nedc,
                'taxable_horsepower' => $data->taxableHorsepower,
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
     * @return list<EnumOptionData>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            static fn (\BackedEnum $case): EnumOptionData => new EnumOptionData(
                value: (string) $case->value,
                label: method_exists($case, 'label')
                    ? $case->label()
                    : (string) $case->value,
            ),
            $cases,
        );
    }
}
