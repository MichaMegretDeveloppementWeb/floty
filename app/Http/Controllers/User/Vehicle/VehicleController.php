<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Actions\Vehicle\ExitVehicleAction;
use App\Actions\Vehicle\ReactivateVehicleAction;
use App\Actions\Vehicle\UpdateVehicleAction;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Data\User\Vehicle\VehicleFormOptionsData;
use App\Data\User\Vehicle\VehicleIndexQueryData;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleUserType;
use App\Http\Controllers\Controller;
use App\Services\Vehicle\VehicleQueryService;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleQueryService $vehicles,
        private readonly VehicleReadRepositoryInterface $vehicleRead,
        private readonly CreateVehicleAction $createVehicle,
        private readonly UpdateVehicleAction $updateVehicle,
        private readonly ExitVehicleAction $exitVehicle,
        private readonly ReactivateVehicleAction $reactivateVehicle,
    ) {}

    public function index(VehicleIndexQueryData $query): Response
    {
        // Sélecteur année **local** à la page (chantier J, ADR-0020).
        // `?year=` URL avec fallback année calendaire courante. Pilote
        // les colonnes financières (« Coût plein YYYY », « Prix location »).
        $year = $query->year ?? $this->resolveDefaultYear();

        return Inertia::render('User/Vehicles/Index/Index', [
            'vehicles' => $this->vehicles->listPaginated($query, $year),
            'options' => [
                'firstRegistrationYearBounds' => $this->vehicles->firstRegistrationYearBounds(),
            ],
            'query' => $query,
            'selectedYear' => $year,
            // Cf. note d'archi sur le bug placeholder : `hasAnyVehicle`
            // distingue « table intrinsèquement vide » du « filtre actif
            // retournant 0 » sans dériver depuis 3 sources désynchronisées.
            'hasAnyVehicle' => $this->vehicleRead->existsAny(),
        ]);
    }

    public function show(int $vehicle, Request $request): Response
    {
        $year = $this->resolveYearFromRequest($request);

        return Inertia::render('User/Vehicles/Show/Index', [
            'vehicle' => $this->vehicles->findVehicleData($vehicle, $year),
            'options' => $this->buildFormOptions(),
            'selectedYear' => $year,
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

    public function edit(int $vehicle, Request $request): Response
    {
        $year = $this->resolveYearFromRequest($request);

        return Inertia::render('User/Vehicles/Edit/Index', [
            'vehicle' => $this->vehicles->findVehicleData($vehicle, $year),
            'options' => $this->buildFormOptions(),
            'selectedYear' => $year,
        ]);
    }

    public function update(int $vehicle, UpdateVehicleData $data): RedirectResponse
    {
        $this->updateVehicle->execute($vehicle, $data);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule mis à jour.');
    }

    public function exit(int $vehicle, ExitVehicleData $data): RedirectResponse
    {
        $this->exitVehicle->execute($vehicle, $data);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule retiré de la flotte.');
    }

    public function reactivate(int $vehicle): RedirectResponse
    {
        $this->reactivateVehicle->execute($vehicle);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule réactivé.');
    }

    /**
     * Résolution de l'année par défaut (year non passé) : année
     * calendaire courante si présente dans la config fiscale, sinon
     * dernière année configurée. Borne de sécurité contre les années
     * non couvertes par les barèmes.
     */
    private function resolveDefaultYear(): int
    {
        $available = array_map('intval', config('floty.fiscal.available_years', []));
        $current = (int) CarbonImmutable::now()->year;

        if (in_array($current, $available, true)) {
            return $current;
        }

        return $available === [] ? $current : (int) max($available);
    }

    /**
     * Résolution de l'année depuis Request (`?year=`) avec fallback.
     */
    private function resolveYearFromRequest(Request $request): int
    {
        $available = array_map('intval', config('floty.fiscal.available_years', []));
        $raw = $request->query('year');
        $candidate = is_numeric($raw) ? (int) $raw : null;

        if ($candidate !== null && in_array($candidate, $available, true)) {
            return $candidate;
        }

        return $this->resolveDefaultYear();
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
