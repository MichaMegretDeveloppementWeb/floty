<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\FiscalRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de l'index `fiscal_rules` en **mode miroir** (chantier κ.6 / ADR-0022).
 *
 * Cf. 02-schema-fiscal.md § 1 + ADR-0006 + ADR-0009 + ADR-0022.
 *
 * **Doctrine** : les classes PHP sont la source de vérité. Cette table
 * n'est qu'un index miroir. Pour chaque année dans
 * {@see FiscalRuleRegistry::registeredYears()}, le seeder :
 *   1. lit les règles **pipeline** depuis le registry (instances PHP),
 *      récupère leur métadonnée via les nouvelles méthodes du contrat
 *      `FiscalRule` (`name`, `description`, `ruleType`, `displayOrder`,
 *      `legalBasis`, `isActive`, `applicabilityStart/End`) et upsert ;
 *   2. ajoute les règles **documentaires-only** (R-2024-001, 006, 007,
 *      009, 020, 022, 023, 024 - règles « hors pipeline » qui n'ont pas
 *      de classe PHP en V1) hard-codées dans
 *      {@see informativeRulesForYear()} pour 2024 ;
 *   3. supprime tout enregistrement BDD pour cette année dont le
 *      `rule_code` n'apparaît ni dans les règles pipeline ni dans les
 *      documentaires (mode miroir : suppression des orphelins).
 *
 * **Préservation historique** : les années qui ne sont **pas**
 * enregistrées dans le registry ne sont pas touchées (lecture seule).
 * Permet de purger une année expérimentale (κ.8 fake 2026) en retirant
 * son boot du config et en ré-exécutant le seeder.
 *
 * Migration ADR-0022 complète des 8 documentaires-only vers des classes
 * PHP : reportée à un chantier post-V1.
 */
final class FiscalRulesSeeder extends Seeder
{
    public function run(FiscalRuleRegistry $registry): void
    {
        foreach ($registry->registeredYears() as $year) {
            // Atomicité par année : si un upsert plante, le mirror delete
            // ne s'exécute pas et on évite un état BDD partiellement
            // synchronisé. L'isolement par-année (vs global) garantit que
            // les autres années restent cohérentes même si une année plante.
            DB::transaction(function () use ($registry, $year): void {
                $syncedCodes = [];

                foreach ($registry->rulesForYear($year) as $rule) {
                    $row = $this->rowFromPhpClass($rule, $year);
                    FiscalRule::updateOrCreate(
                        ['rule_code' => $row['rule_code'], 'fiscal_year' => $row['fiscal_year']],
                        $row,
                    );
                    $syncedCodes[] = $row['rule_code'];
                }

                foreach ($this->informativeRulesForYear($year) as $informative) {
                    FiscalRule::updateOrCreate(
                        ['rule_code' => $informative['rule_code'], 'fiscal_year' => $year],
                        $informative,
                    );
                    $syncedCodes[] = $informative['rule_code'];
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
     * Le `$year` est passé depuis la boucle `registeredYears()` plutôt que
     * dérivé de la règle (évite un `method_exists($rule, 'fiscalYear')`
     * fragile côté seeder, sachant que `fiscalYear()` ne fait pas partie
     * du contrat `FiscalRule` : il vit dans `AnnualRuleTrait`).
     *
     * @return array<string, mixed>
     */
    private function rowFromPhpClass(FiscalRuleContract $rule, int $year): array
    {
        $reflection = new \ReflectionClass($rule);
        $relativePath = str_replace('\\', '/', $reflection->getName()).'.php';
        $codeReference = str_starts_with($relativePath, 'App/')
            ? 'app/'.substr($relativePath, 4)
            : $relativePath;

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
            'code_reference' => $codeReference,
            'display_order' => $rule->displayOrder(),
            'is_active' => $rule->isActive(),
        ];
    }

    /**
     * Règles documentaires-only (hors pipeline) hard-codées par année.
     *
     * Ces règles existent dans `fiscal_rules` pour la page « Règles de
     * calcul » mais n'ont pas de classe PHP en V1. Elles couvrent des
     * principes architecturaux (R-001 redevable, R-007 historisation,
     * R-009 mise hors-service, R-020 loueur, R-022 période contractuelle)
     * ou des cas vides (R-023 abattements 2024) ou des contrôles UI
     * (R-024 Crit'Air).
     *
     * @return list<array<string, mixed>>
     */
    private function informativeRulesForYear(int $year): array
    {
        if ($year !== 2024) {
            return [];
        }

        $cibs = static fn (string $article): array => ['type' => 'CIBS', 'article' => $article];
        $bofip = static fn (string $reference, string $paragraph): array => [
            'type' => 'BOFIP',
            'reference' => $reference,
            'paragraph' => $paragraph,
        ];

        $base = [
            'fiscal_year' => 2024,
            'applicability_start' => '2024-01-01',
            'applicability_end' => '2024-12-31',
            'is_active' => true,
        ];

        $entries = [
            [
                'rule_code' => 'R-2024-001',
                'name' => 'Redevable et fait générateur',
                'description' => "Définit qui est redevable de la taxe (entreprise utilisatrice) et le fait générateur (affectation d'un véhicule à des fins économiques).",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => [TaxType::Co2->value, TaxType::Pollutants->value],
                'legal_basis' => [
                    $cibs('L. 421-95'),
                    $cibs('L. 421-99'),
                ],
                'display_order' => 1,
            ],
            [
                'rule_code' => 'R-2024-006',
                'name' => 'Bascule sur barème PA (CO₂ manquant)',
                'description' => 'Fallback vers le barème Puissance Administrative si la donnée CO₂ est manquante.',
                'rule_type' => RuleType::Classification,
                'taxes_concerned' => [TaxType::Co2->value],
                'legal_basis' => [
                    $cibs('L. 421-119-1'),
                ],
                'display_order' => 6,
            ],
            [
                'rule_code' => 'R-2024-007',
                'name' => 'Historisation des caractéristiques véhicule',
                'description' => "Application de la version effective des caractéristiques fiscales à chaque jour d'affectation.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => [TaxType::Co2->value, TaxType::Pollutants->value],
                'legal_basis' => [
                    $cibs('L. 421-164'),
                ],
                'display_order' => 7,
            ],
            [
                'rule_code' => 'R-2024-009',
                'name' => "Mise hors-service en cours d'année",
                'description' => "Un véhicule cédé ou détruit en cours d'année n'est plus affecté à des fins économiques à compter de sa date de sortie. La proportion annuelle d'affectation est ramenée à la fraction d'année pendant laquelle l'entreprise détenait effectivement le véhicule (date de 1ère immatriculation par l'entreprise → date de sortie). BOFiP § 190 explicite l'exemple d'une entreprise qui acquiert un véhicule au 31/01 et le revend au 30/11.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => [TaxType::Co2->value, TaxType::Pollutants->value],
                'legal_basis' => [
                    $cibs('L. 421-107'),
                    $bofip('BOI-AIS-MOB-10-30-10', '§ 190'),
                ],
                'display_order' => 9,
            ],
            [
                'rule_code' => 'R-2024-020',
                'name' => 'Exonération loueur - redevable = entreprise utilisatrice',
                'description' => "Le loueur (entreprise qui détient les véhicules pour les louer ou mettre à disposition de ses clients) n'est pas redevable de la taxe ; ce sont les entreprises utilisatrices qui le sont. Texte identique pour les deux taxes (L. 421-140 reprend L. 421-128 mot pour mot).",
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => [TaxType::Co2->value, TaxType::Pollutants->value],
                'legal_basis' => [
                    $cibs('L. 421-128'),
                ],
                'display_order' => 20,
            ],
            [
                'rule_code' => 'R-2024-022',
                'name' => 'Période contractuelle vs usage effectif',
                'description' => "Le numérateur du prorata journalier compte le nombre de jours de la période d'affectation contractuelle (date de début → date de fin de contrat), pas le nombre de jours pendant lesquels le véhicule a effectivement circulé ou été utilisé. Un contrat de 30 jours pendant lesquels le véhicule n'a roulé qu'une journée compte 30 jours au numérateur. La doctrine BOFiP § 170 le précise : « le nombre de jours pendant lesquels le véhicule a effectivement circulé n'est donc pas pris en compte ». Seules les indispos réductrices (R-2024-008) viennent diminuer ce numérateur.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => [TaxType::Co2->value, TaxType::Pollutants->value],
                'legal_basis' => [
                    $cibs('L. 421-107'),
                    $bofip('BOI-AIS-MOB-10-30-10', '§ 170'),
                ],
                'display_order' => 22,
            ],
            [
                'rule_code' => 'R-2024-023',
                'name' => 'Aucun abattement isolé applicable en 2024',
                'description' => '2024 : aucun abattement isolé (ex. E85) applicable aux deux taxes annuelles. Confirmé par lecture exhaustive du CIBS et du BOFiP.',
                'rule_type' => RuleType::Abatement,
                'taxes_concerned' => [TaxType::Co2->value, TaxType::Pollutants->value],
                'legal_basis' => [],
                'display_order' => 23,
            ],
            [
                'rule_code' => 'R-2024-024',
                'name' => "Garde-fou Crit'Air",
                'description' => "Contrôle de cohérence entre la catégorie polluants CIBS calculée (E / 1 / les plus polluants, L. 421-134) et la vignette Crit'Air attendue pour le véhicule. La vignette Crit'Air relève du Code de la route (R. 318-2) et de l'arrêté du 21 juin 2016, indépendamment de la fiscalité. Le garde-fou émet une alerte non bloquante en cas d'incohérence afin que l'utilisateur vérifie la saisie.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => [TaxType::Pollutants->value],
                'legal_basis' => [
                    $cibs('L. 421-134'),
                ],
                'display_order' => 24,
                'code_reference' => 'resources/js/Composables/Vehicle/useCritAirCheck.ts',
            ],
        ];

        return array_map(static function (array $entry) use ($base): array {
            $row = [...$base, ...$entry];
            $row['code_reference'] ??= 'app/Fiscal/Year2024/'.str_replace('-', '_', $row['rule_code']).'.php';

            return $row;
        }, $entries);
    }
}
