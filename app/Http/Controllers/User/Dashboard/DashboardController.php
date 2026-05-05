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

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    public function __invoke(Request $request): Response
    {
        // Sélecteur année local au Dashboard (chantier η Phase 5) :
        // `?year=YYYY` URL avec fallback année calendaire courante,
        // borné par le scope dynamique (contrats actifs).
        $year = $this->resolveYear($request);

        return Inertia::render('User/Dashboard/Index/Index', [
            'stats' => $this->stats->computeStats($year),
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
        ]);
    }

    /**
     * Doctrine "données métier ⊥ règles fiscales" : l'utilisateur peut
     * piloter n'importe quelle année calendaire raisonnable (range
     * 1900-2100). Le sélecteur affiche `yearScope.availableYears` mais
     * un deep-link `?year=` libre reste honoré. Fallback : année
     * calendaire courante.
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
