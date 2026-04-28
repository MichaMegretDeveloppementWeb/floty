<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\VehicleFormOptionsData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleUserType;
use App\Fiscal\Resolver\FiscalYearResolver;
use App\Http\Controllers\Controller;
use App\Services\Vehicle\VehicleQueryService;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleQueryService $vehicles,
        private readonly CreateVehicleAction $createVehicle,
        private readonly FiscalYearResolver $fiscalYear,
    ) {}

    public function index(): Response
    {
        return Inertia::render('User/Vehicles/Index/Index', [
            'vehicles' => $this->vehicles->listForFleetView($this->fiscalYear->resolve()),
        ]);
    }

    public function show(int $vehicle): Response
    {
        return Inertia::render('User/Vehicles/Show/Index', [
            'vehicle' => $this->vehicles->findVehicleData($vehicle),
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

        return Inertia::render('User/Vehicles/Create/Index', [
            'options' => $options,
        ]);
    }

    public function store(StoreVehicleData $data): RedirectResponse
    {
        $this->createVehicle->execute($data);

        return redirect()
            ->route('user.vehicles.index')
            ->with('toast-success', 'Véhicule enregistré.');
    }
}
