<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Planning;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Data\User\Planning\PreviewRentalsInputData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekQueryData;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contract;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Planning\PlanningHeatmapService;
use App\Services\Planning\WeekDetailService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning endpoints: annual heatmap, weekly drawer, contract previews
 * and bulk contract creation from the planning wizard.
 */
final class PlanningController extends Controller
{
    public function __construct(
        private readonly PlanningHeatmapService $heatmap,
        private readonly WeekDetailService $weekDetail,
        private readonly BulkCreateContractsAction $bulkCreateContracts,
        private readonly AvailableYearsResolver $availableYears,
        private readonly CompanyReadRepositoryInterface $companies,
    ) {}

    /**
     * Render the fleet-wide annual heatmap.
     *
     * Cost props are deferred in separate Inertia groups so the user sees
     * the cheap cells first (theoretical full-year tax) while the heavier
     * real-cost pipeline runs in parallel.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('view-planning');

        $year = $this->resolveYear($request);
        $sortDirection = $this->resolveSortDirection($request);

        return Inertia::render(
            'User/Planning/Index/Index',
            [
                ...$this->heatmap->buildHeatmap($year, $sortDirection),
                'fullYearCosts' => Inertia::defer(fn () => $this->heatmap->fullYearCostsForVehicles($year), 'fast'),
                'realCosts' => Inertia::defer(fn () => $this->heatmap->realCostsForVehicles($year), 'slow'),
                'monthlyRentals' => Inertia::defer(fn () => $this->heatmap->monthlyRentalTotalsForFleet($year), 'rentals'),
                'selectedYear' => $year,
                'sortDirection' => $sortDirection,
                'yearScope' => YearScopeData::fromResolver($this->availableYears),
            ],
        );
    }

    /**
     * Entry point for the per-company view.
     *
     * Without a company in the URL, redirect to the first company; if no
     * company exists at all, render the empty state.
     */
    public function companyIndexRoot(Request $request): RedirectResponse|Response
    {
        Gate::authorize('view-planning');

        $first = $this->companies->findAllOrderedByName()->first();

        if ($first === null) {
            return Inertia::render('User/Planning/Company/Empty');
        }

        $params = ['company' => $first->id];
        $rawYear = $request->query('year');
        if (is_numeric($rawYear)) {
            $params['year'] = (int) $rawYear;
        }

        return redirect()->route('user.planning.companies.index', $params);
    }

    /**
     * Render the heatmap scoped to a single company.
     *
     * Numeric cells reflect days used by the company; the colour scale
     * keeps tracking the global vehicle occupancy as a disponibility cue.
     */
    public function companyIndex(Request $request, Company $company): Response
    {
        Gate::authorize('view-planning');

        $year = $this->resolveYear($request);
        $sortDirection = $this->resolveSortDirection($request);

        return Inertia::render(
            'User/Planning/Company/Index',
            [
                ...$this->heatmap->buildHeatmapForCompany($year, $company, $sortDirection),
                'fullYearCosts' => Inertia::defer(fn () => $this->heatmap->fullYearCostsForVehicles($year), 'fast'),
                'realCosts' => Inertia::defer(fn () => $this->heatmap->realCostsForVehicles($year, $company->id), 'slow'),
                'monthlyRentals' => Inertia::defer(fn () => $this->heatmap->monthlyRentalTotalsForCompany($year, $company->id), 'rentals'),
                'selectedYear' => $year,
                'sortDirection' => $sortDirection,
                'yearScope' => YearScopeData::fromResolver($this->availableYears),
            ],
        );
    }

    /**
     * Return the week detail payload (anonymised when `companyId` is set).
     */
    public function week(WeekQueryData $query, Request $request): JsonResponse
    {
        Gate::authorize('view-planning');

        $year = $this->resolveYear($request);

        $payload = $query->companyId !== null
            ? $this->weekDetail->buildWeekForCompany($query->vehicleId, $query->week, $year, $query->companyId)
            : $this->weekDetail->buildWeek($query->vehicleId, $query->week, $year);

        return response()->json($payload);
    }

    /**
     * Compute a tax preview for the dates currently selected in the wizard.
     */
    public function previewTaxes(PreviewTaxesInputData $input, Request $request): JsonResponse
    {
        Gate::authorize('view-planning');

        $year = $request->query('year') !== null
            ? $this->resolveYear($request)
            : (int) CarbonImmutable::parse($input->dates[0])->year;

        return response()->json(
            $this->weekDetail->previewTaxes($input, $year),
        );
    }

    /**
     * Compute a standalone rental preview consistent with the final invoice.
     */
    public function previewRentals(PreviewRentalsInputData $input): JsonResponse
    {
        Gate::authorize('view-planning');

        return response()->json(
            $this->weekDetail->previewRentals($input),
        );
    }

    /**
     * Create contracts in bulk on a shared period from the planning wizard.
     *
     * @return JsonResponse `{ createdIds: list<int> }`
     */
    public function storeBulk(BulkStoreContractsData $input): JsonResponse
    {
        Gate::authorize('create', Contract::class);

        $createdIds = $this->bulkCreateContracts->execute($input);

        return response()->json(['createdIds' => $createdIds]);
    }

    /**
     * Resolve `?year=` against a reasonable calendar range, falling back to
     * the current year. The selector UI exposes `yearScope`, but deep links
     * to any year in [1900, 2100] are honoured.
     */
    private function resolveYear(Request $request): int
    {
        $raw = $request->query('year');
        $candidate = is_numeric($raw) ? (int) $raw : null;

        if ($candidate !== null && $candidate >= 1900 && $candidate <= 2100) {
            return $candidate;
        }

        return $this->availableYears->currentYear();
    }

    /**
     * Resolve `?direction=` strictly to `'asc'` or `'desc'` (default
     * `'asc'`). Drives the order of the vehicle column on the heatmap.
     */
    private function resolveSortDirection(Request $request): string
    {
        return $request->query('direction') === 'desc' ? 'desc' : 'asc';
    }
}
