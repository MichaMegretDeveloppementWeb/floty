<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Actions\Vehicle\UpdateVehicleAction;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Data\User\Vehicle\VehicleFormOptionsData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
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
        private readonly UpdateVehicleAction $updateVehicle,
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
            'vehicle' => $this->vehicles->findVehicleData($vehicle, $this->fiscalYear->resolve()),
            'options' => $this->buildFormOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Vehicles/Create/Index', [
            'options' => $this->buildFormOptions(),
        ]);
    }

    public function store(StoreVehicleData $data): RedirectResponse
    {
        $this->createVehicle->execute($data);

        return redirect()
            ->route('user.vehicles.index')
            ->with('toast-success', 'Véhicule enregistré.');
    }

    public function edit(int $vehicle): Response
    {
        return Inertia::render('User/Vehicles/Edit/Index', [
            'vehicle' => $this->vehicles->findVehicleData($vehicle, $this->fiscalYear->resolve()),
            'options' => $this->buildFormOptions(),
        ]);
    }

    public function update(int $vehicle, UpdateVehicleData $data): RedirectResponse
    {
        $this->updateVehicle->execute($vehicle, $data);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule mis à jour.');
    }

    private function buildFormOptions(): VehicleFormOptionsData
    {
        return new VehicleFormOptionsData(
            receptionCategories: EnumOptions::fromCases(ReceptionCategory::cases()),
            vehicleUserTypes: EnumOptions::fromCases(VehicleUserType::cases()),
            bodyTypes: EnumOptions::fromCases(BodyType::cases()),
            energySources: EnumOptions::fromCases(EnergySource::cases()),
            underlyingCombustionEngineTypes: EnumOptions::fromCases(UnderlyingCombustionEngineType::cases()),
            euroStandards: EnumOptions::fromCases(EuroStandard::cases()),
            homologationMethods: EnumOptions::fromCases(HomologationMethod::cases()),
            pollutantCategories: EnumOptions::fromCases(PollutantCategory::cases()),
        );
    }
}
