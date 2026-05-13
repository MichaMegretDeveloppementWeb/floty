<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\FiscalRule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de l'index `fiscal_rules` en **mode miroir** (chantier κ.6 +
 * Phase 13 D5.11 · ADR-0022 finalisée).
 *
 * Cf. 02-schema-fiscal.md § 1 + ADR-0006 + ADR-0009 + ADR-0022.
 *
 * **Doctrine** · les classes PHP sont la source de vérité unique. Cette
 * table n'est qu'un index miroir. Pour chaque année dans
 * {@see FiscalRuleRegistry::registeredYears()}, le seeder ·
 *   1. récupère le {@see FiscalYearBoot} de l'année (depuis le container
 *      via la config `floty.fiscal.year_boots`) ;
 *   2. lit les règles **pipeline** via `$boot->rules()` (calculatoires,
 *      utilisées par le moteur fiscal), instancie chaque classe et upsert
 *      sa métadonnée ;
 *   3. lit les règles **documentaires-only** via `$boot->informativeRules()`
 *      (Phase 13 D5.11 · règles qui portent une connaissance fiscale
 *      mais ne participent pas au pipeline · cf. {@see InformativeRule}),
 *      instancie et upsert avec le même process ;
 *   4. supprime tout enregistrement BDD pour cette année dont le
 *      `rule_code` n'apparaît dans aucune des deux listes (mode miroir ·
 *      suppression des orphelins).
 *
 * **Plus aucune donnée hardcodée** · contrairement à la version Phase 11,
 * le seeder ne contient plus de métadonnée fiscale en clair (description,
 * base légale, URLs, ordre d'affichage). Toute l'information vit dans la
 * classe PHP de chaque règle (cf. {@see App\Fiscal\Year2024} et le détail
 * du format dans {@see App\Fiscal\Contracts\FiscalRule::legalBasis()}).
 *
 * **Préservation historique** · les années qui ne sont **pas** enregistrées
 * dans le registry ne sont pas touchées (lecture seule). Permet de purger
 * une année expérimentale en retirant son boot du config et en
 * ré-exécutant le seeder.
 */
final class FiscalRulesSeeder extends Seeder
{
    public function __construct(
        private readonly Container $resolver,
    ) {}

    public function run(FiscalRuleRegistry $registry): void
    {
        // Indexe les boots déclarés via config pour récupérer les
        // règles documentaires par année. Le registry, lui, ne connaît
        // que les règles **pipeline** (cf. ADR-0006 · le pipeline ne
        // touche jamais aux règles documentaires). Si une année est
        // enregistrée dans le registry sans boot associé (cas du
        // test end-to-end qui injecte des stubs via `register()`), le
        // seeder seede uniquement ses règles pipeline.
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

            // Atomicité par année · si un upsert plante, le mirror delete
            // ne s'exécute pas et on évite un état BDD partiellement
            // synchronisé. L'isolement par-année garantit que les autres
            // années restent cohérentes même si une année plante.
            DB::transaction(function () use ($registry, $boot, $year): void {
                $syncedCodes = [];

                foreach ($registry->rulesForYear($year) as $rule) {
                    $row = $this->rowFromPhpClass($rule);
                    FiscalRule::updateOrCreate(
                        ['rule_code' => $row['rule_code'], 'fiscal_year' => $row['fiscal_year']],
                        $row,
                    );
                    $syncedCodes[] = $row['rule_code'];
                }

                if ($boot !== null) {
                    foreach ($boot->informativeRules() as $informativeClass) {
                        $informative = $this->resolver->make($informativeClass);
                        $row = $this->rowFromPhpClass($informative);
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
     * Construit la ligne BDD à partir d'une instance PHP.
     *
     * L'année fiscale est lue via `applicabilityStart()` plutôt que via
     * une méthode dédiée `fiscalYear()` · `applicabilityStart()` fait
     * partie du contrat `FiscalRule` (alors que `fiscalYear()` vit dans
     * `AnnualRuleTrait` et n'est pas garanti par le contrat).
     *
     * Le `code_reference` est dérivé automatiquement du FQCN de la
     * classe. Cas spécial · R-2024-024 (Crit'Air) pointe vers le
     * composable Vue qui porte l'implémentation effective · l'override
     * est porté par la classe elle-même via la méthode publique
     * `codeReference()` si elle existe.
     *
     * @return array<string, mixed>
     */
    private function rowFromPhpClass(FiscalRuleContract $rule): array
    {
        $year = $rule->applicabilityStart()->year;

        return [
            'rule_code' => $rule->ruleCode(),
            'name' => $rule->name(),
            'description' => $rule->description(),
            'fiscal_year' => $year,
            'rule_type' => $rule->ruleType(),
            'taxes_concerned' => array_map(
                static fn (TaxType $t): string => $t->value,
                $rule->taxesConcerned(),
            ),
            'applicability_start' => $rule->applicabilityStart()->toDateString(),
            'applicability_end' => $rule->applicabilityEnd()?->toDateString(),
            'legal_basis' => $rule->legalBasis(),
            'code_reference' => $this->codeReferenceFor($rule),
            'display_order' => $rule->displayOrder(),
            'is_active' => $rule->isActive(),
        ];
    }

    /**
     * Résout le chemin du code source pour une règle. Par défaut · path
     * relatif dérivé du FQCN (`App\Fiscal\Year2024\X` → `app/Fiscal/Year2024/X.php`).
     *
     * Une règle peut **override** ce comportement en exposant une
     * méthode publique `codeReference(): string` retournant un chemin
     * arbitraire (utile pour les règles dont l'implémentation effective
     * vit hors PHP, ex. R-2024-024 Crit'Air implémenté dans
     * `resources/js/Composables/Vehicle/useCritAirCheck.ts`).
     */
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
