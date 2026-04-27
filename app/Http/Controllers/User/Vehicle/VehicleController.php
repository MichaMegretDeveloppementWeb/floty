<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\VehicleFormOptionsData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleUserType;
use App\Http\Controllers\Controller;
use App\Services\Vehicle\StoreVehicleService;
use App\Services\Vehicle\VehicleQueryService;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleQueryService $vehicles,
        private readonly StoreVehicleService $store,
    ) {}

    public function index(): Response
    {
        return Inertia::render('User/Vehicles/Index', [
            'vehicles' => $this->vehicles->listForFleetView(
                (int) config('floty.fiscal.current_year'),
            ),
        ]);
    }

    public function create(): Response
    {
        $options = new VehicleFormOptionsData(
            receptionCategories: EnumOptions::fromCases(ReceptionCategory::cases()),
            vehicleUserTypes: EnumOptions::fromCases(VehicleUserType::cases()),
            bodyTypes: EnumOptions::fromCases(BodyType::cases()),
            energySources: EnumOptions::fromCases(EnergySource::cases()),
            euroStandards: EnumOptions::fromCases(EuroStandard::cases()),
            homologationMethods: EnumOptions::fromCases(HomologationMethod::cases()),
            pollutantCategories: EnumOptions::fromCases(PollutantCategory::cases()),
        );

        return Inertia::render('User/Vehicles/Create', [
            'options' => $options,
        ]);
    }

    public function store(StoreVehicleData $data): RedirectResponse
    {
        $this->store->createWithInitialFiscalCharacteristics($data);

        return redirect()
            ->route('user.vehicles.index')
            ->with('toast-success', 'Véhicule enregistré.');
    }
}
