<?php

namespace App\Http\Controllers\User\FiscalRule;

use App\Http\Controllers\Controller;
use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page consultation « Règles de calcul » — lecture seule.
 *
 * Le contenu de la table `fiscal_rules` est peuplé par seeder
 * ({@see FiscalRulesSeeder}, ADR-0002).
 */
final class FiscalRuleController extends Controller
{
    public function index(): Response
    {
        $availableYears = FiscalRule::query()
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->all();

        $requestedYear = request()->query('year');
        $year = $requestedYear !== null
            ? (int) $requestedYear
            : ($availableYears[0] ?? now()->year);

        $rules = FiscalRule::query()
            ->where('fiscal_year', $year)
            ->orderBy('display_order')
            ->get()
            ->map(static fn (FiscalRule $r) => [
                'id' => $r->id,
                'ruleCode' => $r->rule_code,
                'name' => $r->name,
                'description' => $r->description,
                'ruleType' => $r->rule_type->value,
                'taxesConcerned' => $r->taxes_concerned,
                'legalBasis' => $r->legal_basis,
                'isActive' => $r->is_active,
            ]);

        return Inertia::render('User/FiscalRules/Index', [
            'fiscalYear' => $year,
            'rules' => $rules,
            'availableYears' => $availableYears,
        ]);
    }
}
