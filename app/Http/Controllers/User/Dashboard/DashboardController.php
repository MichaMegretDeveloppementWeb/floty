<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
    ) {}

    public function __invoke(Request $request): Response
    {
        // Sélecteur année local au Dashboard (chantier J, ADR-0020) :
        // `?year=YYYY` URL avec fallback année calendaire courante.
        // Borné côté front à `availableYears` (shared prop).
        $year = $this->resolveYear($request);

        return Inertia::render('User/Dashboard/Index/Index', [
            'stats' => $this->stats->computeStats($year),
            'selectedYear' => $year,
        ]);
    }

    private function resolveYear(Request $request): int
    {
        $available = array_map('intval', config('floty.fiscal.available_years', []));
        $raw = $request->query('year');
        $candidate = is_numeric($raw) ? (int) $raw : null;

        if ($candidate !== null && in_array($candidate, $available, true)) {
            return $candidate;
        }

        $current = (int) CarbonImmutable::now()->year;
        if (in_array($current, $available, true)) {
            return $current;
        }

        // Fallback ultime : dernière année configurée (cas rare où la
        // config fiscale ne couvre pas l'année calendaire courante).
        return $available === [] ? $current : (int) max($available);
    }
}
