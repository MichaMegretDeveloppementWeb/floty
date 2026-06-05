<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Company;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\Company\UpdateCompanyAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Data\User\Company\StoreCompanyData;
use App\Data\User\Company\UpdateCompanyData;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Exceptions\Company\CompanyShortCodeCollisionException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Billing\PendingInvoicesResolver;
use App\Services\Company\CompanyAggregatesService;
use App\Services\Company\CompanyDetailService;
use App\Services\Company\CompanyListingService;
use App\Services\Contract\ContractQueryService;
use App\Services\Driver\DriverQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\Declaration\DeclarationLifecycleResolver;
use App\Services\Fiscal\Declaration\PendingDeclarationsResolver;
use App\Services\Planning\PlanningHeatmapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyDetailService $companyDetail,
        private readonly CompanyAggregatesService $companyAggregates,
        private readonly CompanyListingService $companyListing,
        private readonly CompanyReadRepositoryInterface $companyRead,
        private readonly DriverQueryService $drivers,
        private readonly ContractQueryService $contracts,
        private readonly CreateCompanyAction $createCompany,
        private readonly UpdateCompanyAction $updateCompany,
        private readonly AvailableYearsResolver $availableYears,
        private readonly PendingDeclarationsResolver $pendingDeclarations,
        private readonly PendingInvoicesResolver $pendingInvoices,
        private readonly DeclarationLifecycleResolver $declarationLifecycle,
        private readonly PlanningHeatmapService $planningHeatmap,
    ) {}

    /**
     * JSON endpoint returning 12 monthly NET rental totals for the company × year.
     */
    public function monthlyRentals(Company $company, Request $request): JsonResponse
    {
        Gate::authorize('view', $company);

        $year = (int) $request->query('year', (string) CarbonImmutable::now()->year);

        return response()->json([
            'year' => $year,
            'rentals' => $this->planningHeatmap->monthlyRentalTotalsForCompany($year, $company->id),
        ]);
    }

    /**
     * List companies with filters; the costs prop is deferred.
     */
    public function index(CompanyIndexQueryData $query): Response
    {
        Gate::authorize('viewAny', Company::class);

        $year = $this->resolveSelectedYear($query->year);

        $companies = $this->companyListing->listPaginatedSlim($query, $year);
        $companyIds = array_map(static fn ($c): int => $c->id, $companies->data);

        return Inertia::render('User/Companies/Index/Index', [
            'companies' => $companies,
            'costs' => Inertia::defer(
                fn () => $this->companyListing->costsForCompanyIds($companyIds, $year),
            ),
            'query' => $query,
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
            'hasAnyCompany' => $this->companyRead->existsAny(),
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
     * Render the company detail page with tab-aware lazy props.
     *
     * The `?tab=` query string drives which props are loaded eagerly at
     * mount. Inactive tabs are exposed via `Inertia::optional()` and only
     * pull their SQL on the partial reload triggered by the front-end
     * when the user opens the tab for the first time.
     */
    public function show(Company $company, ContractIndexQueryData $contractsQuery, Request $request): Response
    {
        Gate::authorize('view', $company);

        $activeTab = (string) $request->query('tab', 'overview');
        $isOverview = $activeTab === 'overview';
        $companyId = $company->id;

        // Slim base always (header + every tab + Drivers tab list). The
        // heavy overview payload (multi-year fiscal aggregation) is gated
        // to the overview tab.
        $detail = $this->companyDetail->detailBase($company);

        // Resolved once · needed by the (always-eager) pending resolvers
        // that feed the nav "à faire" badges, and by the overview payload.
        $activeYears = $this->contracts->findActiveYearsForCompany($companyId);

        // Contracts tab: default to the current real year when no explicit
        // period query param is set. Only `year` is forced so the backend
        // can derive periodStart/End via `effectivePeriod()` and the UI
        // distinguishes "year mode" from a custom range.
        $hasExplicitPeriod = $request->exists('year')
            || $request->exists('periodStart')
            || $request->exists('periodEnd');

        if (! $hasExplicitPeriod) {
            $contractsQuery->year = $detail->currentRealYear;
        }

        // Unified `?year=` shared between the Fiscal and Billing tabs.
        $selectedYear = (int) $request->query('year', (string) $detail->currentRealYear);

        return Inertia::render('User/Companies/Show/Index', [
            'company' => $detail,
            // Overview-tab-only heavy payload (KPIs, lifetime, history,
            // activity). Eager only when ?tab=overview, optional otherwise.
            'companyOverview' => $this->eagerForTab(
                $isOverview,
                fn () => $this->companyDetail->overview($company, $activeYears),
            ),
            'contractsQuery' => $contractsQuery,
            'billingYear' => $selectedYear,
            // Kept eager (every tab): feed the nav "à faire" badges + the
            // fiscal/billing year dots, not only the overview card.
            'pendingDeclarations' => $this->pendingDeclarations->pendingForCompany($companyId, $activeYears),
            'pendingInvoices' => $this->pendingInvoices->pendingForCompany($companyId, $activeYears),
            // Continuous year range shared between billing and contracts pills
            // (one cheap SQL query, kept eager).
            'contractsAvailableYears' => $this->contracts->availableYearsRangeForCompany(
                $companyId,
                $detail->currentRealYear,
            ),

            'contracts' => $this->eagerForTab(
                $activeTab === 'contracts',
                fn () => $this->contracts->listPaginatedForCompany($companyId, $contractsQuery),
            ),
            'contractsStats' => $this->eagerForTab(
                $activeTab === 'contracts',
                fn () => $this->contracts->statsForCompany(
                    $companyId,
                    $contractsQuery->effectivePeriod()['periodStart'],
                    $contractsQuery->effectivePeriod()['periodEnd'],
                ),
            ),

            'options' => [
                'drivers' => $this->eagerForTab(
                    $activeTab === 'drivers',
                    fn () => $this->drivers->listForOptions(),
                ),
            ],

            'companyFiscal' => $this->eagerForTab(
                $activeTab === 'fiscal',
                fn () => $this->companyAggregates->fiscalBreakdownForYear($companyId, $selectedYear),
            ),
            'declarationLifecycle' => $this->eagerForTab(
                $activeTab === 'fiscal',
                fn () => $this->declarationLifecycle->resolveForCompanyYear($companyId, $selectedYear),
            ),

            'companyBilling' => $this->eagerForTab(
                $activeTab === 'billing',
                fn () => $this->companyAggregates->billingForYear($companyId, $selectedYear),
            ),
            'companyRentalDiscounts' => $this->eagerForTab(
                $activeTab === 'billing',
                fn () => $this->companyAggregates->rentalDiscountsForCompany($companyId),
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
     * Render the company creation form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('User/Companies/Create/Index', [
            'colors' => $this->companyListing->colorOptions(),
        ]);
    }

    /**
     * Persist a new company.
     */
    public function store(StoreCompanyData $data): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        try {
            $this->createCompany->execute($data);
        } catch (CompanyShortCodeCollisionException $e) {
            throw ValidationException::withMessages([
                'legal_name' => $e->getUserMessage(),
            ]);
        }

        return redirect()
            ->route('user.companies.index')
            ->with('toast-success', 'Entreprise créée.');
    }

    /**
     * Render the company edit form with a slim detail payload.
     */
    public function edit(Company $company): Response
    {
        Gate::authorize('update', $company);

        $detail = $this->companyDetail->detailForEdit($company);

        return Inertia::render('User/Companies/Edit/Index', [
            'company' => $detail,
            'colors' => $this->companyListing->colorOptions(),
        ]);
    }

    /**
     * Update an existing company.
     */
    public function update(Company $company, UpdateCompanyData $data): RedirectResponse
    {
        Gate::authorize('update', $company);

        $this->updateCompany->execute($company->id, $data);

        return redirect()
            ->route('user.companies.show', ['company' => $company->id])
            ->with('toast-success', 'Entreprise mise à jour.');
    }
}
