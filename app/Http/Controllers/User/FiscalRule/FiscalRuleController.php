<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalRule;

use App\Http\Controllers\Controller;
use App\Services\FiscalRule\FiscalRuleQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page consultation « Règles de calcul » - lecture seule.
 *
 * Sélecteur d'année **local** à la page (chantier J, ADR-0020) :
 * `?year=YYYY` URL avec fallback **dernière année configurée** (les
 * barèmes sont versionnés ; un utilisateur qui ouvre la page sans
 * paramètre veut consulter le barème le plus récent disponible).
 */
final class FiscalRuleController extends Controller
{
    public function __construct(
        private readonly FiscalRuleQueryService $rules,
    ) {}

    public function index(Request $request): Response
    {
        $year = $this->resolveYear($request);

        return Inertia::render('User/FiscalRules/Index/Index', [
            'rules' => $this->rules->listForYear($year),
            'selectedYear' => $year,
        ]);
    }

    /**
     * Pour FiscalRules : fallback **dernière année configurée** (pas
     * l'année calendaire courante), car l'utilisateur consulte des
     * barèmes versionnés. Cette page n'a pas de notion de « présent ».
     */
    private function resolveYear(Request $request): int
    {
        $available = array_map('intval', config('floty.fiscal.available_years', []));
        $raw = $request->query('year');
        $candidate = is_numeric($raw) ? (int) $raw : null;

        if ($candidate !== null && in_array($candidate, $available, true)) {
            return $candidate;
        }

        return $available === [] ? 0 : (int) max($available);
    }
}
