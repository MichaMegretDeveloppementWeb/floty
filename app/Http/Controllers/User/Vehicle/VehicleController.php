<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Actions\Vehicle\ExitVehicleAction;
use App\Actions\Vehicle\ReactivateVehicleAction;
use App\Actions\Vehicle\UpdateVehicleAction;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
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
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Vehicle\VehicleQueryService;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    public function index(VehicleIndexQueryData $query): Response
    {
        // Sélecteur année **local** à la page (chantier η Phase 3) —
        // bornes alimentées par `AvailableYearsResolver` (scope global
        // dynamique calculé depuis les contrats, pas la config statique
        // morte). `?year=` URL validé contre ce scope, fallback
        // `currentYear` si invalide.
        $year = $this->resolveSelectedYear($query->year);

        return Inertia::render('User/Vehicles/Index/Index', [
            'vehicles' => $this->vehicles->listPaginated($query, $year),
            'options' => [
                'firstRegistrationYearBounds' => $this->vehicles->firstRegistrationYearBounds(),
            ],
            'query' => $query,
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
            // Cf. note d'archi sur le bug placeholder : `hasAnyVehicle`
            // distingue « table intrinsèquement vide » du « filtre actif
            // retournant 0 » sans dériver depuis 3 sources désynchronisées.
            'hasAnyVehicle' => $this->vehicleRead->existsAny(),
        ]);
    }

    /**
     * Doctrine temporelle (chantier η Phase 3) — résolution `?year=`
     * URL contre le scope global dynamique, fallback `currentYear` si
     * invalide ou absent.
     */
    private function resolveSelectedYear(?int $requested): int
    {
        if ($requested !== null && in_array($requested, $this->availableYears->availableYears(), true)) {
            return $requested;
        }

        return $this->availableYears->currentYear();
    }

    public function show(int $vehicle): Response
    {
        // Doctrine temporelle (chantier η Phase 2 — refonte onglets) :
        // `usageStats` est initialisé sur `currentYear`. Le sélecteur
        // d'année des cartes Utilisation et Fiscalité fetch en lazy via
        // `usageStatsForYear` / `fullYearBreakdownForYear` côté front
        // avec cache client.
        return Inertia::render('User/Vehicles/Show/Index', [
            'vehicle' => $this->vehicles->findVehicleData($vehicle),
            'options' => $this->buildFormOptions(),
        ]);
    }

    /**
     * Endpoint lazy JSON appelé par `useYearLazy` côté front quand
     * l'utilisateur change l'année dans la carte Utilisation & Répartition
     * de la fiche véhicule. Retourne `VehicleUsageStatsData` pour
     * l'année demandée — Timeline + Breakdown par entreprise + breakdown
     * Coût plein imbriqué.
     */
    public function usageStats(int $vehicle, Request $request): JsonResponse
    {
        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);

        return response()->json($this->vehicles->usageStatsForYear($vehicle, $year));
    }

    /**
     * Endpoint lazy JSON pour le panel Coût plein de l'onglet Fiscalité.
     * Retourne uniquement `VehicleFullYearTaxBreakdownData` (panel détaillé
     * du calcul théorique 100 % d'utilisation).
     */
    public function fullYearBreakdown(int $vehicle, Request $request): JsonResponse
    {
        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);

        return response()->json($this->vehicles->fullYearBreakdownForYear($vehicle, $year));
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
            'vehicle' => $this->vehicles->findVehicleData($vehicle),
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
