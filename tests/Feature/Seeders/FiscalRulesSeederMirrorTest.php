<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le mode miroir du `FiscalRulesSeeder` (Phase 13 D5.14 ·
 * ADR-0022 finalisée v1.4 · BDD = index minimal).
 *
 * Depuis D5.14, la table `fiscal_rules` ne contient plus que les
 * colonnes d'index (`id`, `rule_code`, `fiscal_year`, `code_reference`).
 * Les métadonnées fiscales (name, description, legal_basis, etc.)
 * vivent exclusivement dans les classes PHP, lues via le registry.
 *
 * Le seeder en mode miroir garantit donc uniquement ·
 *   - 24 entrées d'index pour 2024 (16 pipeline + 8 documentaires)
 *   - chaque entrée pointe vers la bonne classe PHP via `code_reference`
 *   - idempotence (seed → seed → état identique)
 *   - mode miroir · entrée orpheline supprimée au reseed
 *   - préservation · années non enregistrées non touchées
 */
final class FiscalRulesSeederMirrorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function post_seed_2024_a_24_lignes(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        self::assertSame(24, FiscalRule::query()->where('fiscal_year', 2024)->count());
    }

    #[Test]
    public function code_reference_pointe_vers_la_classe_php_attendue(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        // R-2024-002 · classe pipeline standard
        $r002 = FiscalRule::query()->where('rule_code', 'R-2024-002')->firstOrFail();
        self::assertSame(
            'app/Fiscal/Year2024/Transversal/R2024_002_DailyProrata.php',
            $r002->code_reference,
        );

        // R-2024-021 · classe pipeline avec wrapper LCD
        $r021 = FiscalRule::query()->where('rule_code', 'R-2024-021')->firstOrFail();
        self::assertSame(
            'app/Fiscal/Year2024/Exemption/R2024_021_ShortTermRental.php',
            $r021->code_reference,
        );

        // R-2024-001 · classe documentaire
        $r001 = FiscalRule::query()->where('rule_code', 'R-2024-001')->firstOrFail();
        self::assertSame(
            'app/Fiscal/Year2024/Transversal/R2024_001_TaxpayerAndTriggeringEvent.php',
            $r001->code_reference,
        );

        // R-2024-024 · override `codeReference()` vers le composable Vue
        $r024 = FiscalRule::query()->where('rule_code', 'R-2024-024')->firstOrFail();
        self::assertSame(
            'resources/js/Composables/Vehicle/useCritAirCheck.ts',
            $r024->code_reference,
        );
    }

    #[Test]
    public function idempotence_re_seed_produit_le_meme_etat(): void
    {
        $this->seed(FiscalRulesSeeder::class);
        $hashFirst = $this->hashAllRows();

        $this->seed(FiscalRulesSeeder::class);
        $hashSecond = $this->hashAllRows();

        self::assertSame($hashFirst, $hashSecond);
    }

    #[Test]
    public function mirror_delete_supprime_les_orphelins_de_l_annee_synchronisee(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        FiscalRule::factory()->create([
            'rule_code' => 'R-2024-999',
            'fiscal_year' => 2024,
            'code_reference' => 'app/Fake.php',
        ]);

        self::assertSame(25, FiscalRule::query()->where('fiscal_year', 2024)->count());

        $this->seed(FiscalRulesSeeder::class);

        self::assertSame(24, FiscalRule::query()->where('fiscal_year', 2024)->count());
        self::assertFalse(
            FiscalRule::query()
                ->where('rule_code', 'R-2024-999')
                ->where('fiscal_year', 2024)
                ->exists(),
        );
    }

    #[Test]
    public function annee_non_enregistree_dans_le_registry_n_est_pas_touchee(): void
    {
        FiscalRule::factory()->create([
            'rule_code' => 'R-2090-LEGACY',
            'fiscal_year' => 2090,
            'code_reference' => 'app/Fake.php',
        ]);

        $this->seed(FiscalRulesSeeder::class);

        self::assertTrue(
            FiscalRule::query()
                ->where('rule_code', 'R-2090-LEGACY')
                ->where('fiscal_year', 2090)
                ->exists(),
        );
    }

    private function hashAllRows(): string
    {
        $rows = FiscalRule::query()
            ->orderBy('fiscal_year')
            ->orderBy('rule_code')
            ->get(['rule_code', 'fiscal_year', 'code_reference'])
            ->toArray();

        return md5(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
