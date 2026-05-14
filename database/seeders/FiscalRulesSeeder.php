<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\FiscalRule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de l'index `fiscal_rules` en mode miroir minimal (Phase 13
 * D5.14 · ADR-0022 finalisée v1.4).
 *
 * **Doctrine** · la table `fiscal_rules` est un INDEX strictement
 * minimal qui relie l'id BDD à la classe PHP via `code_reference`.
 * Toute la métadonnée fiscale (name, description, legal_basis,
 * pedagogical_content, etc.) vit exclusivement dans les classes PHP
 * et est lue via le registry au runtime. Plus aucun miroir BDD.
 *
 * **Algorithme** · pour chaque année déclarée dans `floty.fiscal.year_boots`,
 * lire pipeline rules + informative rules, et upsert l'index `(rule_code,
 * fiscal_year, code_reference)`. Mode miroir conservé · les entrées
 * orphelines de l'année sont supprimées.
 *
 * **Effet net** · `seed → seed → seed` produit toujours le même état
 * BDD. Retirer une classe PHP + `seed` = entrée index disparaît.
 */
final class FiscalRulesSeeder extends Seeder
{
    public function __construct(
        private readonly Container $resolver,
    ) {}

    public function run(FiscalRuleRegistry $registry): void
    {
        $bootClasses = (array) config('floty.fiscal.year_boots', []);
        $bootsByYear = [];
        foreach ($bootClasses as $bootClass) {
            if (! is_string($bootClass) || ! is_subclass_of($bootClass, FiscalYearBoot::class)) {
                continue;
            }
            $boot = $this->resolver->make($bootClass);
            $bootsByYear[$boot->year()] = $boot;
        }

        foreach ($registry->registeredYears() as $year) {
            $boot = $bootsByYear[$year] ?? null;

            DB::transaction(function () use ($registry, $boot, $year): void {
                $syncedCodes = [];

                foreach ($registry->rulesForYear($year) as $rule) {
                    $row = $this->rowFromPhpClass($rule, $year);
                    FiscalRule::updateOrCreate(
                        ['rule_code' => $row['rule_code'], 'fiscal_year' => $row['fiscal_year']],
                        $row,
                    );
                    $syncedCodes[] = $row['rule_code'];
                }

                if ($boot !== null) {
                    foreach ($boot->informativeRules() as $informativeClass) {
                        $informative = $this->resolver->make($informativeClass);
                        $row = $this->rowFromPhpClass($informative, $year);
                        FiscalRule::updateOrCreate(
                            ['rule_code' => $row['rule_code'], 'fiscal_year' => $row['fiscal_year']],
                            $row,
                        );
                        $syncedCodes[] = $row['rule_code'];
                    }
                }

                FiscalRule::query()
                    ->where('fiscal_year', $year)
                    ->whereNotIn('rule_code', $syncedCodes)
                    ->delete();
            });
        }
    }

    /**
     * Construit la ligne BDD à partir d'une instance PHP · index
     * minimal (rule_code · fiscal_year · code_reference).
     *
     * Le `code_reference` est dérivé automatiquement du FQCN. Une
     * règle peut **override** en exposant `codeReference(): string`
     * (utile pour les règles dont l'implémentation effective vit
     * hors PHP, ex. R-2024-024 Crit'Air dans `useCritAirCheck.ts`).
     *
     * @return array<string, mixed>
     */
    private function rowFromPhpClass(FiscalRuleContract $rule, int $year): array
    {
        return [
            'rule_code' => $rule->ruleCode(),
            'fiscal_year' => $year,
            'code_reference' => $this->codeReferenceFor($rule),
        ];
    }

    private function codeReferenceFor(FiscalRuleContract $rule): string
    {
        if (method_exists($rule, 'codeReference')) {
            return $rule->codeReference();
        }

        $relativePath = str_replace('\\', '/', $rule::class).'.php';

        return str_starts_with($relativePath, 'App/')
            ? 'app/'.substr($relativePath, 4)
            : $relativePath;
    }
}
