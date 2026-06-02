<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Dashboard;

use App\Data\Shared\YearScopeData;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Fiscal\AvailableYearsResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard landing page.
 *
 * Built around two lenses plus pending tasks:
 *   - Present (`kpis`): YTD KPIs pinned to the current calendar year
 *     and compared with the same period one year earlier.
 *   - Evolution (`history`): the same KPIs across the past few years.
 *   - Tasks (`pendingTasks`): top declarations and invoices to process.
 *
 * Most props are loaded progressively via `Inertia::defer` so the initial
 * paint is minimal. Evolution tabs other than the cheapest one are
 * exposed via `Inertia::optional` and hydrated on tab click.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
    ) {}

    /**
     * Render the dashboard with deferred + optional props.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('view-dashboard');

        $year = $this->resolveYear($request);
        $currentYear = $this->availableYears->currentYear();

        return Inertia::render('User/Dashboard/Index/Index', [
            'kpis' => Inertia::defer(fn () => $this->stats->computeKpisFiscal($currentYear), 'kpis'),
            'kpisRecettes' => Inertia::defer(fn () => $this->stats->computeKpisRecettes($currentYear), 'kpisRecettes'),
            'pendingTasks' => Inertia::defer(fn () => $this->stats->computePendingTasks(), 'pendingTasks'),
            'historyJoursVehicule' => Inertia::defer(fn () => $this->stats->computeHistoryJoursVehicule(), 'historyJoursVehicule'),
            'historyContracts' => Inertia::optional(fn () => $this->stats->computeHistoryContracts()),
            'historyTaxes' => Inertia::optional(fn () => $this->stats->computeHistoryTaxes()),
            'historyRecettes' => Inertia::optional(fn () => $this->stats->computeHistoryRecettes()),
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromResolverAndRegistry($this->availableYears, $this->fiscalRules),
        ]);
    }

    /**
     * Resolve the selected year from the request, falling back to the current year.
     *
     * The Present KPIs ignore this selection; only the Evolution lens
     * reads it via the page-local pill selector.
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
}
