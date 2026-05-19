<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalRule;

use App\Data\Shared\YearScopeData;
use App\Data\User\Fiscal\FiscalRuleTabData;
use App\Enums\Fiscal\RuleTab;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Http\Controllers\Controller;
use App\Services\FiscalRule\FiscalRuleQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only fiscal rules viewer.
 *
 * Year selector is local to the page (`?year=`) with a fallback to the
 * latest year registered in the rule registry: the rules are versioned
 * and a user landing without a query param wants the most recent ruleset.
 */
final class FiscalRuleController extends Controller
{
    public function __construct(
        private readonly FiscalRuleQueryService $rules,
        private readonly FiscalRuleRegistry $registry,
    ) {}

    /**
     * Render the rules page for the resolved year.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('view-fiscal-rules');

        $year = $this->resolveYear($request);

        return Inertia::render('User/FiscalRules/Index/Index', [
            'rules' => $this->rules->listForYear($year),
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromRegistry($this->registry),
            'tabs' => array_map(
                static fn (RuleTab $t): FiscalRuleTabData => FiscalRuleTabData::fromEnum($t),
                RuleTab::cases(),
            ),
        ]);
    }

    /**
     * Resolve the active year, falling back to the latest registered ruleset.
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
