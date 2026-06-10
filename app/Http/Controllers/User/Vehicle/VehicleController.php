<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Actions\Vehicle\ExitVehicleAction;
use App\Actions\Vehicle\ReactivateVehicleAction;
use App\Actions\Vehicle\UpdateVehicleAction;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
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
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Http\Controllers\Controller;
use App\Managers\VehicleRegistryLookup\VehicleRegistryLookupManager;
use App\Models\Vehicle;
use App\Services\Control\VehicleControlsService;
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
        private readonly VehicleDetailService $vehicleDetail,
        private readonly VehicleAggregatesService $vehicleAggregates,
        private readonly VehicleListingService $vehicleListing,
        private readonly VehicleReadRepositoryInterface $vehicleRead,
        private readonly VehicleEventReadRepositoryInterface $vehicleEventRead,
        private readonly VehicleYearlyPricingReadRepositoryInterface $vehiclePricings,
        private readonly CreateVehicleAction $createVehicle,
        private readonly UpdateVehicleAction $updateVehicle,
        private readonly ExitVehicleAction $exitVehicle,
        private readonly ReactivateVehicleAction $reactivateVehicle,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRegistry,
        private readonly VehicleRegistryLookupManager $registryLookup,
        private readonly VehicleControlsService $vehicleControls,
    ) {}

    /**
     * List vehicles. Heavy cost columns are deferred to a follow-up request.
     */
    public function index(VehicleIndexQueryData $query): Response
    {
        Gate::authorize('viewAny', Vehicle::class);

        $year = $this->resolveSelectedYear($query->year);

        $vehicles = $this->vehicleListing->listPaginatedSlim($query);
        $vehicleIds = array_map(static fn ($v): int => $v->id, $vehicles->data);

        return Inertia::render('User/Vehicles/Index/Index', [
            'vehicles' => $vehicles,
            'vehiclesCosts' => Inertia::defer(
                fn () => $this->vehicleListing->costsForVehicleIds($vehicleIds, $year),
            ),
            // Per-row regulatory-control badge (overdue / due controls), resolved
            // batched for the page (bounded queries, no N+1). Eager, unlike the
            // costs: a due/overdue control is visible on first paint with no
            // follow-up round-trip nor skeleton, and the bounded scan is cheap
            // enough not to delay the response. Today-based (not year-scoped),
            // so it rides every list visit (sort / filter / page) automatically.
            'controlsBadges' => $this->vehicleControls->badgesForVehicleIds($vehicleIds, CarbonImmutable::today()),
            'options' => [
                'firstRegistrationYearBounds' => $this->vehicleListing->firstRegistrationYearBounds(),
            ],
            'query' => $query,
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
            'hasAnyVehicle' => $this->vehicleRead->existsAny(),
        ]);
    }

    /**
     * Resolve `?year=` against the dynamic global scope, falling back to current year.
     */
    private function resolveSelectedYear(?int $requested): int
    {
        if ($requested !== null && in_array($requested, $this->availableYears->availableYears(), true)) {
            return $requested;
        }

        return $this->availableYears->currentYear();
    }

    /**
     * Render the vehicle detail page with tab-aware lazy props.
     */
    public function show(int $vehicle, Request $request): Response
    {
        // Single load with fiscal history + yearly pricings, threaded to
        // every page service below. Avoids each service re-querying the
        // same vehicle row (and its VFC/pricings) within the request.
        $vehicleModel = $this->vehicleRead->findByIdWithFiscalHistory($vehicle);
        Gate::authorize('view', $vehicleModel);

        // Single events load, threaded to the always-eager base DTO and (when
        // ?tab=overview) the overview payload, so the two no longer query the
        // same vehicle_events / documents rows twice within one request.
        // Timeline variant: the events tab renders the « Détails » lines.
        $vehicleEvents = $this->vehicleEventRead->findForVehicleTimeline($vehicle);

        $vehicleData = $this->vehicleDetail->findVehicleData($vehicleModel, $vehicleEvents);

        // Unified `?year=` shared between the Fiscal and Billing tabs.
        $selectedYear = (int) $request->query('year', (string) $vehicleData->kpiYear);

        $activeTab = (string) $request->query('tab', 'overview');
        $isOverview = $activeTab === 'overview';

        return Inertia::render('User/Vehicles/Show/Index', [
            'vehicle' => $vehicleData,
            // Eager (independent of the active tab): drives the count badge on
            // the "Contrôles réglementaires" tab label, so a due/overdue control
            // is visible without opening the tab. Bounded one-vehicle resolution.
            'controlsBadge' => $this->vehicleControls->dueBadgeForVehicle($vehicleModel, CarbonImmutable::today()),
            // Overview-tab-only payload (usage timeline, current-year KPI,
            // busy dates, multi-year history). Eager only when
            // ?tab=overview, Inertia::optional otherwise (tab-gating). The
            // N fiscal pipelines no longer run on the other tabs nor leak
            // via the former `history` defer.
            'vehicleOverview' => $this->eagerForTab(
                $isOverview,
                fn () => $this->vehicleDetail->overviewForVehicle($vehicleModel, $vehicleEvents),
            ),
            'options' => $this->buildFormOptions(),
            'billingYear' => $selectedYear,
            'fiscalYear' => $selectedYear,
            // Fiscal tab year scope depends on the registry (years with rule
            // sets) rather than on contract data; a vehicle can be inspected
            // for any registered year even without contracts.
            'fiscalYearScope' => YearScopeData::fromRegistry($this->fiscalRegistry),

            'fiscalYearBreakdown' => $this->eagerForTab(
                $activeTab === 'fiscal',
                fn () => $this->vehicleAggregates->fullYearBreakdownForYear($vehicleModel, $selectedYear),
            ),

            'vehicleBilling' => $this->eagerForTab(
                $activeTab === 'billing',
                fn () => $this->vehicleAggregates->billingForYear($vehicleModel->id, $selectedYear),
            ),

            // Per-vehicle regulatory controls (Chantier B / B2). Not year-scoped:
            // échéances are forward-looking dates, computed on the fly.
            'vehicleControls' => $this->eagerForTab(
                $activeTab === 'controls',
                fn () => $this->vehicleControls->buildForVehicle($vehicleModel, CarbonImmutable::today()),
            ),
        ]);
    }

    /**
     * Lazy+cumulative tab loading helper: eager when active, optional otherwise.
     */
    private function eagerForTab(bool $isActive, callable $resolver): mixed
    {
        return $isActive ? $resolver() : Inertia::optional($resolver);
    }

    /**
     * Lazy JSON endpoint for the Usage & Repartition card.
     *
     * 404s (ModelNotFoundException) when the vehicle no longer exists.
     */
    public function usageStats(int $vehicle, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);
        $vehicleModel = $this->vehicleRead->findByIdWithFiscalHistory($vehicle);

        return response()->json($this->vehicleAggregates->usageStatsForYear($vehicleModel, $year));
    }

    /**
     * Lazy JSON endpoint for the Full-year tax panel in the Fiscal tab.
     */
    public function fullYearBreakdown(int $vehicle, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);
        $vehicleModel = $this->vehicleRead->findByIdWithFiscalHistory($vehicle);

        return response()->json($this->vehicleAggregates->fullYearBreakdownForYear($vehicleModel, $year));
    }

    /**
     * AJAX endpoint returning `{ cents, year, fallback }` for the contract form.
     *
     * Unlike usageStats/fullYearBreakdown which degrade silently, this one
     * signals a missing vehicle with a 404 so the composable can render a
     * neutral state.
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

    /**
     * AJAX endpoint returning daily/weekly/monthly rates for vehicle × year.
     *
     * Each rate is `null` when no pricing row exists for the requested year.
     */
    public function yearlyRates(Vehicle $vehicle, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);
        $pricing = $this->vehiclePricings->findForVehicleAndYear($vehicle->id, $year);

        return response()->json([
            'year' => $year,
            'dailyCents' => $pricing?->daily_rate_cents,
            'weeklyCents' => $pricing?->weekly_rate_cents,
            'monthlyCents' => $pricing?->monthly_rate_cents,
        ]);
    }

    /**
     * Render the vehicle creation form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Vehicle::class);

        return Inertia::render('User/Vehicles/Create/Index', [
            'options' => $this->buildFormOptions(),
            'registryLookupEnabled' => $this->registryLookup->isAvailable(),
        ]);
    }

    /**
     * Persist a new vehicle.
     */
    public function store(StoreVehicleData $data): RedirectResponse
    {
        Gate::authorize('create', Vehicle::class);

        $this->createVehicle->execute($data);

        return redirect()
            ->route('user.vehicles.index')
            ->with('toast-success', 'Véhicule enregistré.');
    }

    /**
     * Render the vehicle edit form.
     */
    public function edit(int $vehicle): Response
    {
        $vehicleModel = $this->vehicleRead->findByIdWithFiscalHistory($vehicle);
        Gate::authorize('update', $vehicleModel);

        return Inertia::render('User/Vehicles/Edit/Index', [
            'vehicle' => $this->vehicleDetail->findVehicleData(
                $vehicleModel,
                $this->vehicleEventRead->findForVehicle($vehicle),
            ),
            'options' => $this->buildFormOptions(),
        ]);
    }

    /**
     * Update an existing vehicle.
     */
    public function update(int $vehicle, UpdateVehicleData $data): RedirectResponse
    {
        $vehicleModel = $this->vehicleRead->findOrFail($vehicle);
        Gate::authorize('update', $vehicleModel);

        $this->updateVehicle->execute($vehicle, $data);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule mis à jour.');
    }

    /**
     * Mark the vehicle as exited from the fleet.
     */
    public function exit(int $vehicle, ExitVehicleData $data): RedirectResponse
    {
        $vehicleModel = $this->vehicleRead->findOrFail($vehicle);
        Gate::authorize('update', $vehicleModel);

        $this->exitVehicle->execute($vehicle, $data);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule retiré de la flotte.');
    }

    /**
     * Reactivate an exited vehicle.
     */
    public function reactivate(int $vehicle): RedirectResponse
    {
        $vehicleModel = $this->vehicleRead->findOrFail($vehicle);
        Gate::authorize('update', $vehicleModel);

        $this->reactivateVehicle->execute($vehicle);

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle])
            ->with('toast-success', 'Véhicule réactivé.');
    }

    /**
     * Build the enum option payload shared between the create and edit forms.
     */
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
