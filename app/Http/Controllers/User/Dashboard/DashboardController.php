<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Dashboard;

use App\Data\Shared\YearScopeData;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Fiscal\AvailableYearsResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard utilisateur · page d'accueil de l'app.
 *
 * Refondu chantier η Phase 4 selon doctrine 2 lentilles + tâches ·
 *   - Présent (`kpis`) · 4 KPIs YTD figés sur l'année calendaire
 *     courante + comparaison vs même période Y-1.
 *   - Évolution (`history`) · 4 mêmes KPIs déclinés par année sur
 *     les N dernières années (graphique barres côté UI).
 *   - Tâches (`pendingTasks`) · top 5 déclarations et factures à
 *     traiter sur la flotte multi-entreprises (Phase 13 D5.15).
 *
 * Le sélecteur d'année top-right (encore basé sur `useLocalYearSelector`)
 * pilote la lentille « Évolution » mise en surbrillance, mais les
 * KPIs Présent restent figés sur l'année calendaire courante
 * (doctrine HD7 · Présent ne dépend pas du sélecteur).
 *
 * **S2.3 (plan optim perf 2026-05-15)** · `history` et `pendingTasks`
 * sont **deferred** (Inertia v3) · ne participent pas à la 1ère
 * peinture, arrivent dans une 2e requête asynchrone après le mount.
 * `kpis` reste eager (carte top of fold, primary view). Skeleton CSS
 * côté Vue le temps des deferred props.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    public function __invoke(Request $request): Response
    {
        Gate::authorize('view-dashboard');

        $year = $this->resolveYear($request);

        return Inertia::render('User/Dashboard/Index/Index', [
            'kpis' => $this->stats->computeKpis($this->availableYears->currentYear()),
            // S2.3 · history (8 ans pipeline · ~600 ms) et pendingTasks
            // (5 SQL · ~150 ms) sont deferred · sortis du chemin
            // critique 1ère peinture. Inertia v3 déclenche une 2e
            // requête automatique après le mount, le frontend rend
            // un skeleton entre-temps via `<Deferred>`.
            'history' => Inertia::defer(fn () => $this->stats->computeHistory()),
            'pendingTasks' => Inertia::defer(fn () => $this->stats->computePendingTasks()),
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
        ]);
    }

    /**
     * Doctrine "données métier ⊥ règles fiscales" : l'utilisateur peut
     * piloter n'importe quelle année calendaire raisonnable. Le
     * sélecteur UI affiche `yearScope` (scope contrats), mais un
     * deep-link `?year=` libre reste honoré. Fallback année calendaire
     * courante.
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
