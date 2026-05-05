<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalRule;

use App\Data\Shared\YearScopeData;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Http\Controllers\Controller;
use App\Services\FiscalRule\FiscalRuleQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page consultation « Règles de calcul » - lecture seule.
 *
 * Sélecteur d'année **local** à la page (chantier η Phase 5) : `?year=`
 * URL avec fallback **dernière année enregistrée dans le registry des
 * règles** (les barèmes sont versionnés ; un utilisateur qui ouvre la
 * page sans paramètre veut consulter le barème le plus récent
 * disponible). Le scope provient du moteur fiscal, pas du scope contrats.
 */
final class FiscalRuleController extends Controller
{
    public function __construct(
        private readonly FiscalRuleQueryService $rules,
        private readonly FiscalRuleRegistry $registry,
    ) {}

    public function index(Request $request): Response
    {
        $year = $this->resolveYear($request);

        return Inertia::render('User/FiscalRules/Index/Index', [
            'rules' => $this->rules->listForYear($year),
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromRegistry($this->registry),
        ]);
    }

    /**
     * Pour FiscalRules : fallback **dernière année enregistrée** (pas
     * l'année calendaire courante), car l'utilisateur consulte des
     * barèmes versionnés. Cette page n'a pas de notion de « présent ».
     */
    private function resolveYear(Request $request): int
    {
        $available = $this->registry->registeredYears();
        $raw = $request->query('year');
        $candidate = is_numeric($raw) ? (int) $raw : null;

        if ($candidate !== null && in_array($candidate, $available, true)) {
            return $candidate;
        }

        return $available === [] ? 0 : (int) max($available);
    }
}
