<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalRule;

use App\Http\Controllers\Controller;
use App\Services\FiscalRule\FiscalRuleQueryService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page consultation « Règles de calcul » — lecture seule.
 *
 * L'année affichée suit toujours `config('floty.fiscal.current_year')`
 * (= shared props `fiscal.currentYear` côté front). Aucun fallback sur
 * `now()->year` : la cohérence visuelle entre toutes les pages prime.
 */
final class FiscalRuleController extends Controller
{
    public function __construct(private readonly FiscalRuleQueryService $rules) {}

    public function index(): Response
    {
        return Inertia::render('User/FiscalRules/Index/Index', [
            'rules' => $this->rules->listForYear(
                (int) config('floty.fiscal.current_year'),
            ),
        ]);
    }
}
