<?php

namespace App\Http\Controllers\User\FiscalRule;

use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Http\Controllers\Controller;
use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

/**
 * Page consultation « Règles de calcul » — lecture seule.
 *
 * Le contenu de la table `fiscal_rules` est peuplé par seeder
 * ({@see FiscalRulesSeeder}, ADR-0002).
 *
 * L'année affichée suit toujours `config('floty.fiscal.current_year')`
 * (= shared props `fiscal.currentYear` côté front). Aucun fallback sur
 * `now()->year` : la cohérence visuelle entre toutes les pages prime
 * sur l'année calendaire réelle tant qu'une seule année de règles est
 * codée dans le projet.
 */
final class FiscalRuleController extends Controller
{
    public function index(): Response
    {
        $year = (int) config('floty.fiscal.current_year');

        $rules = FiscalRule::query()
            ->where('fiscal_year', $year)
            ->orderBy('display_order')
            ->get()
            ->map(static fn (FiscalRule $r): FiscalRuleListItemData => new FiscalRuleListItemData(
                id: $r->id,
                ruleCode: $r->rule_code,
                name: $r->name,
                description: $r->description,
                ruleType: $r->rule_type,
                taxesConcerned: $r->taxes_concerned,
                legalBasis: $r->legal_basis,
                isActive: $r->is_active,
            ))
            ->values()
            ->all();

        return Inertia::render('User/FiscalRules/Index', [
            'rules' => FiscalRuleListItemData::collect($rules, DataCollection::class),
        ]);
    }
}
