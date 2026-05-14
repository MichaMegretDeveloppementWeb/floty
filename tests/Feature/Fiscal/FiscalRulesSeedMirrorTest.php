<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Fiscal\Contracts\FiscalYearBoot;
use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que la table `fiscal_rules` est en miroir parfait du code
 * PHP (pipeline + informatives), conformément à ADR-0022 v1.4 (PUSH-5 ·
 * audit fiscal renforcé 14/05/2026).
 *
 * **Doctrine** · la BDD est un index minimal `(rule_code, fiscal_year,
 * code_reference)`. Toute la métadonnée vit dans les classes PHP, mais
 * l'index BDD permet la persistance des FK (FiscalReviewDecision,
 * etc.) et la jointure rapide.
 *
 * **Invariant testé** · pour chaque année déclarée dans
 * `floty.fiscal.year_boots`, la BDD contient exactement les codes
 * concaténés `pipeline ∪ informatives` du Boot. Aucun code orphelin,
 * aucun code manquant.
 *
 * **Si le test casse** · soit une règle a été ajoutée/retirée au code
 * sans `php artisan db:seed --class=FiscalRulesSeeder`, soit l'algo
 * mirror du seeder s'est désynchronisé (à investiguer).
 */
final class FiscalRulesSeedMirrorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function table_fiscal_rules_est_miroir_parfait_de_chaque_year_boot(): void
    {
        // Run le seeder dans le contexte de test (RefreshDatabase a
        // déjà nettoyé la BDD · seed dans cet état frais).
        $this->seed(FiscalRulesSeeder::class);

        $bootClasses = (array) config('floty.fiscal.year_boots', []);
        self::assertNotEmpty($bootClasses, 'Au moins un Year{YYYY}Boot doit être configuré.');

        foreach ($bootClasses as $bootClass) {
            self::assertIsString($bootClass);
            /** @var FiscalYearBoot $boot */
            $boot = new $bootClass;
            $year = $boot->year();

            // Codes attendus · concaténation pipeline + informatives.
            // Certaines règles ont une dépendance constructeur (ex.
            // R-2025-008 dépend de R-2025-021) · on passe par le
            // container Laravel pour la résolution.
            $expectedCodes = array_merge(
                array_map(
                    fn (string $class) => $this->app->make($class)->ruleCode(),
                    $boot->rules(),
                ),
                array_map(
                    fn (string $class) => $this->app->make($class)->ruleCode(),
                    $boot->informativeRules(),
                ),
            );
            sort($expectedCodes);

            $actualCodes = FiscalRule::query()
                ->where('fiscal_year', $year)
                ->orderBy('rule_code')
                ->pluck('rule_code')
                ->all();
            sort($actualCodes);

            self::assertSame(
                $expectedCodes,
                $actualCodes,
                sprintf(
                    'BDD fiscal_rules année %d doit être miroir parfait du code PHP.',
                    $year,
                ),
            );
        }
    }

    #[Test]
    public function seed_est_idempotent_double_passage_donne_meme_etat_bdd(): void
    {
        $this->seed(FiscalRulesSeeder::class);
        $first = FiscalRule::query()->orderBy('id')->pluck('rule_code')->all();

        $this->seed(FiscalRulesSeeder::class);
        $second = FiscalRule::query()->orderBy('id')->pluck('rule_code')->all();

        self::assertSame($first, $second, 'Seed → seed → seed doit produire le même état.');
    }

    #[Test]
    public function aucune_regle_bdd_ne_pointe_vers_un_fichier_inexistant(): void
    {
        // `code_reference` est un PATH relatif vers le fichier PHP de
        // la règle (ex. `app/Fiscal/Year2024/.../R2024_004_xxx.php`).
        // On vérifie que chaque PATH référencé en BDD existe bien sur
        // le disque · sinon la métadonnée pointe vers le néant.
        $this->seed(FiscalRulesSeeder::class);
        $base = base_path();

        $rows = FiscalRule::query()->get(['rule_code', 'code_reference', 'fiscal_year']);
        foreach ($rows as $row) {
            $relative = (string) $row->code_reference;
            $absolute = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            self::assertFileExists(
                $absolute,
                sprintf(
                    'BDD pointe vers un fichier PHP inexistant · %s (année %d) → %s',
                    $row->rule_code,
                    $row->fiscal_year,
                    $relative,
                ),
            );
        }
    }
}
