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
 * **Chargement progressif** (chantier perf Dashboard 2026-05-17 v4 ·
 * refonte lazy par onglet selon doctrine `feedback_lazy_tab_loading`) ·
 *
 *   - `kpis` + `kpisRecettes` + `pendingTasks` + `historyJoursVehicule`
 *     en `Inertia::defer` · une seule vague auto-load post-mount
 *     (~500-800 ms). Le 1er onglet du graphique Évolution
 *     (Jours-véhicule, cheap · 1 contracts query) est chargé d'emblée
 *     pour que la section ne soit pas vide.
 *   - `historyContracts` / `historyTaxes` / `historyRecettes` en
 *     `Inertia::optional` · hydratés au clic d'onglet du graphique via
 *     `router.reload({only: ['historyXxx']})`. Skeleton chart pendant
 *     le fetch · cache intra-session (un onglet déjà chargé ne refait
 *     pas la query au retour).
 *
 * Le payload initial Inertia est minimal (juste `selectedYear` +
 * `yearScope`).
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
        $currentYear = $this->availableYears->currentYear();

        return Inertia::render('User/Dashboard/Index/Index', [
            // Chaque prop `Inertia::defer` dans son PROPRE groupe ·
            // 4 requêtes follow-up parallèles · chaque section hydrate
            // dès que SA réponse arrive (UX progressive · le rapide
            // apparaît avant le lent). En prod multi-worker · vrai
            // parallélisme. En dev mono-worker · les requêtes sérialisent
            // mais chaque section apparaît à son rythme.
            'kpis' => Inertia::defer(fn () => $this->stats->computeKpisFiscal($currentYear), 'kpis'),
            'kpisRecettes' => Inertia::defer(fn () => $this->stats->computeKpisRecettes($currentYear), 'kpisRecettes'),
            'pendingTasks' => Inertia::defer(fn () => $this->stats->computePendingTasks(), 'pendingTasks'),
            // History · 1er onglet (Jours-véhicule, cheap) auto-load
            // dans son groupe dédié · les 3 autres onglets sont
            // `Inertia::optional` · hydratés au clic d'onglet côté Vue
            // via `router.reload({only: ['historyXxx']})`.
            'historyJoursVehicule' => Inertia::defer(fn () => $this->stats->computeHistoryJoursVehicule(), 'historyJoursVehicule'),
            'historyContracts' => Inertia::optional(fn () => $this->stats->computeHistoryContracts()),
            'historyTaxes' => Inertia::optional(fn () => $this->stats->computeHistoryTaxes()),
            'historyRecettes' => Inertia::optional(fn () => $this->stats->computeHistoryRecettes()),
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
