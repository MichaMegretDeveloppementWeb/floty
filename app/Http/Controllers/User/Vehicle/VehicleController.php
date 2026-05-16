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
use App\Models\Vehicle;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Vehicle\VehicleAggregatesService;
use App\Services\Vehicle\VehicleDetailService;
use App\Services\Vehicle\VehicleListingService;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(
        // Lot 4 D09 (F-14-003) · `VehicleQueryService` éclaté en 3
        // sous-services thématiques par concern (Detail / Aggregates /
        // Listing). Chaque méthode du controller pointe désormais sur
        // le service avec la bonne responsabilité.
        private readonly VehicleDetailService $vehicleDetail,
        private readonly VehicleAggregatesService $vehicleAggregates,
        private readonly VehicleListingService $vehicleListing,
        private readonly VehicleReadRepositoryInterface $vehicleRead,
        private readonly CreateVehicleAction $createVehicle,
        private readonly UpdateVehicleAction $updateVehicle,
        private readonly ExitVehicleAction $exitVehicle,
        private readonly ReactivateVehicleAction $reactivateVehicle,
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    public function index(VehicleIndexQueryData $query): Response
    {
        Gate::authorize('viewAny', Vehicle::class);

        // Sélecteur année **local** à la page (chantier η Phase 3) ·
        // bornes alimentées par `AvailableYearsResolver` (scope global
        // dynamique calculé depuis les contrats, pas la config statique
        // morte). `?year=` URL validé contre ce scope, fallback
        // `currentYear` si invalide.
        $year = $this->resolveSelectedYear($query->year);

        return Inertia::render('User/Vehicles/Index/Index', [
            'vehicles' => $this->vehicleListing->listPaginated($query, $year),
            'options' => [
                'firstRegistrationYearBounds' => $this->vehicleListing->firstRegistrationYearBounds(),
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
     * Doctrine temporelle (chantier η Phase 3) · résolution `?year=`
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

    public function show(int $vehicle, Request $request): Response
    {
        $vehicleModel = Vehicle::query()->findOrFail($vehicle);
        Gate::authorize('view', $vehicleModel);

        // Doctrine temporelle (chantier η Phase 2 · refonte onglets) :
        // `usageStats` est initialisé sur `currentYear`. Le sélecteur
        // de la carte Utilisation (Vue d'ensemble) reste en lazy fetch
        // JSON via `usageStatsForYear` (cache client `useYearLazy`).
        $vehicleData = $this->vehicleDetail->findVehicleData($vehicle);

        // D5.10.U · param URL **unifié** `?year=` partagé entre les
        // onglets Fiscalité et Facturation de la fiche véhicule.
        $selectedYear = (int) $request->query('year', (string) $vehicleData->kpiYear);

        // D5.10.V · onglets à chargement lazy + cumulatif. Lit `?tab=`
        // pour décider quelles props sont eager au mount initial. Les
        // autres tirent leur SQL QUE sur partial reload côté front.
        $activeTab = (string) $request->query('tab', 'overview');

        return Inertia::render('User/Vehicles/Show/Index', [
            // Eager · props partagées (Vue d'ensemble + header + sync URL year).
            'vehicle' => $vehicleData,
            'options' => $this->buildFormOptions(),
            'billingYear' => $selectedYear,
            'fiscalYear' => $selectedYear,

            // Onglet "fiscal" · breakdown taxe pleine.
            'fiscalYearBreakdown' => $this->eagerForTab(
                $activeTab === 'fiscal',
                fn () => $this->vehicleAggregates->fullYearBreakdownForYear($vehicle, $selectedYear),
            ),

            // Onglet "billing" · récap mensuel.
            'vehicleBilling' => $this->eagerForTab(
                $activeTab === 'billing',
                fn () => $this->vehicleAggregates->billingForYear($vehicle, $selectedYear),
            ),
        ]);
    }

    /**
     * D5.10.V · Helper pour le chargement lazy + cumulatif des onglets.
     */
    private function eagerForTab(bool $isActive, callable $resolver): mixed
    {
        return $isActive ? $resolver() : Inertia::optional($resolver);
    }

    /**
     * Endpoint lazy JSON appelé par `useYearLazy` côté front quand
     * l'utilisateur change l'année dans la carte Utilisation & Répartition
     * de la fiche véhicule. Retourne `VehicleUsageStatsData` pour
     * l'année demandée · Timeline + Breakdown par entreprise + breakdown
     * Taxe pleine imbriqué.
     */
    public function usageStats(int $vehicle, Request $request): JsonResponse
    {
        // Endpoint lazy JSON · Gate::authorize sur la collection plutôt
        // que sur l'instance. Si le véhicule n'existe pas, le service
        // retourne un DTO neutre (pas de 404 sur ces endpoints lazy).
        Gate::authorize('viewAny', Vehicle::class);

        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);

        return response()->json($this->vehicleAggregates->usageStatsForYear($vehicle, $year));
    }

    /**
     * Endpoint lazy JSON pour le panel Taxe pleine de l'onglet Fiscalité.
     * Retourne uniquement `VehicleFullYearTaxBreakdownData` (panel détaillé
     * du calcul théorique 100 % d'utilisation).
     */
    public function fullYearBreakdown(int $vehicle, Request $request): JsonResponse
    {
        // Endpoint lazy JSON · Gate::authorize sur la collection (cf. usageStats).
        Gate::authorize('viewAny', Vehicle::class);

        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);

        return response()->json($this->vehicleAggregates->fullYearBreakdownForYear($vehicle, $year));
    }

    /**
     * Endpoint AJAX léger pour le formulaire Create/Edit Contract ·
     * Calcul A · taxe pleine année d'un véhicule sélectionné, déclenché
     * à la volée par le composable frontend `useVehicleFullYearTax`
     * quand l'utilisateur change le véhicule ou la date de début.
     *
     * Retourne `{ cents, year, fallback }` ou `404` si le véhicule
     * n'existe pas (différent de `usageStats`/`fullYearBreakdown` qui
     * dégradent silencieusement · ici on veut un signal clair pour
     * le composable, qui affiche un état neutre si null).
     *
     * Audit perf 2026-05-16 S2.5 · remplace le pré-calcul lourd de
     * `VehicleOptionData::fullYearTaxByYear` (192 pipeline runs par
     * mount Create/Edit) par un seul appel à la sélection véhicule.
     */
    public function fullYearTax(Vehicle $vehicle, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);
        $result = $this->vehicleListing->fullYearTaxForVehicle($vehicle, $year);

        if ($result === null) {
            return response()->json(['cents' => null, 'year' => $year, 'fallback' => false]);
        }

        return response()->json($result);
    }

    public function create(): Response
    {
        Gate::authorize('create', Vehicle::class);

        return Inertia::render('User/Vehicles/Create/Index', [
            'options' => $this->buildFormOptions(),
        ]);
    }

    public function store(StoreVehicleData $data): RedirectResponse
    {
        Gate::authorize('create', Vehicle::class);

        $this->createVehicle->execute($data);

        return redirect()
            ->route('user.vehicles.index')
            ->with('toast-success', 'Véhicule enregistré.');
    }

    public function edit(int $vehicle): Response
    {
        $vehicleModel = Vehicle::query()->findOrFail($vehicle);
        Gate::authorize('update', $vehicleModel);

        return Inertia::render('User/Vehicles/Edit/Index', [
            'vehicle' => $this->vehicleDetail->findVehicleData($vehicle),
            'options' => $this->buildFormOptions(),
        ]);
    }

    public function update(int $vehicle, UpdateVehicleData $data): RedirectResponse
    {
        $vehicleModel = Vehicle::query()->findOrFail($vehicle);
        Gate::authorize('update', $vehicleModel);

        $this->updateVehicle->execute($vehicle, $data);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule mis à jour.');
    }

    public function exit(int $vehicle, ExitVehicleData $data): RedirectResponse
    {
        $vehicleModel = Vehicle::query()->findOrFail($vehicle);
        Gate::authorize('update', $vehicleModel);

        $this->exitVehicle->execute($vehicle, $data);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule retiré de la flotte.');
    }

    public function reactivate(int $vehicle): RedirectResponse
    {
        $vehicleModel = Vehicle::query()->findOrFail($vehicle);
        Gate::authorize('update', $vehicleModel);

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
