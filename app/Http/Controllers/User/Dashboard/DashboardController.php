<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Dashboard;

use App\Data\Shared\YearScopeData;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Fiscal\AvailableYearsResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard utilisateur — page d'accueil de l'app.
 *
 * Refondu chantier η Phase 4 selon doctrine 3 lentilles :
 *   - Présent (`kpis`) : 4 KPIs YTD figés sur l'année calendaire
 *     courante + comparaison vs même période Y-1.
 *   - Évolution (`history`) : 4 mêmes KPIs déclinés par année sur
 *     les N dernières années (graphique barres côté UI).
 *   - Exploration (`activity`) : heatmap 30 derniers jours flotte +
 *     top 3 véhicules par taxe YTD.
 *
 * Le sélecteur d'année top-right (encore basé sur `useLocalYearSelector`)
 * pilote la lentille « Évolution » mise en surbrillance, mais les
 * KPIs Présent et l'Exploration restent figés sur l'année calendaire
 * courante (doctrine HD7 : Présent ne dépend pas du sélecteur).
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    public function __invoke(Request $request): Response
    {
        $year = $this->resolveYear($request);

        return Inertia::render('User/Dashboard/Index/Index', [
            'kpis' => $this->stats->computeKpis($this->availableYears->currentYear()),
            'history' => $this->stats->computeHistory(),
            'activity' => $this->stats->computeActivity(),
            'pendingTasks' => $this->stats->computePendingTasks(),
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
