<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le mode miroir du `FiscalRulesSeeder` (chantier κ.6 / ADR-0022).
 *
 * Vérifie :
 *  - Post-seed : 24 lignes pour 2024 (16 pipeline lus depuis PHP + 8
 *    documentaires hard-codées).
 *  - Métadonnées strictement identiques au pré-κ.6 sur des règles
 *    représentatives (pipeline transversal, exemption longue, inactive,
 *    informative).
 *  - Idempotence : un second run produit le même état.
 *  - Update : la valeur PHP écrase une modification BDD manuelle.
 *  - Mirror delete : un orphelin pour 2024 est supprimé.
 *  - Préservation : les règles d'une année non enregistrée (2090) ne
 *    sont pas touchées.
 *  - Inactive flag : R-2024-018 et R-2024-019 ont `is_active = false`.
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
    public function metadonnees_pipeline_lues_depuis_php_correspondent_aux_classes(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        // R-2024-002 : transversal court (de PHP)
        $r002 = FiscalRule::query()
            ->where('rule_code', 'R-2024-002')
            ->where('fiscal_year', 2024)
            ->firstOrFail();
        self::assertSame('Prorata journalier (366 jours en 2024)', $r002->name);
        self::assertSame(RuleType::Transversal, $r002->rule_type);
        self::assertSame(2, $r002->display_order);
        self::assertTrue($r002->is_active);
        self::assertSame([TaxType::Co2->value, TaxType::Pollutants->value], $r002->taxes_concerned);

        // R-2024-008 : exemption avec description longue (de PHP)
        $r008 = FiscalRule::query()
            ->where('rule_code', 'R-2024-008')
            ->where('fiscal_year', 2024)
            ->firstOrFail();
        self::assertStringContainsString('réputé ne pas être affecté à des fins économiques', $r008->description);
        self::assertCount(4, $r008->legal_basis);

        // R-2024-021 : LCD (de PHP)
        $r021 = FiscalRule::query()
            ->where('rule_code', 'R-2024-021')
            ->where('fiscal_year', 2024)
            ->firstOrFail();
        self::assertSame('Exonération LCD (location de courte durée)', $r021->name);
        self::assertSame(RuleType::Exemption, $r021->rule_type);
    }

    #[Test]
    public function regles_inactives_ont_is_active_false(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        $r018 = FiscalRule::query()
            ->where('rule_code', 'R-2024-018')
            ->where('fiscal_year', 2024)
            ->firstOrFail();
        $r019 = FiscalRule::query()
            ->where('rule_code', 'R-2024-019')
            ->where('fiscal_year', 2024)
            ->firstOrFail();

        self::assertFalse($r018->is_active);
        self::assertFalse($r019->is_active);

        // Sanity : les autres règles sont actives.
        self::assertTrue(FiscalRule::query()
            ->where('rule_code', 'R-2024-002')
            ->where('fiscal_year', 2024)
            ->firstOrFail()
            ->is_active);
    }

    #[Test]
    public function metadonnees_documentaires_hard_codees_present_dans_la_bdd(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        // R-2024-001 : règle documentaire (hors pipeline)
        $r001 = FiscalRule::query()
            ->where('rule_code', 'R-2024-001')
            ->where('fiscal_year', 2024)
            ->firstOrFail();
        self::assertSame('Redevable et fait générateur', $r001->name);

        // R-2024-024 : Crit'Air avec code_reference custom (UI)
        $r024 = FiscalRule::query()
            ->where('rule_code', 'R-2024-024')
            ->where('fiscal_year', 2024)
            ->firstOrFail();
        self::assertSame('resources/js/Composables/Vehicle/useCritAirCheck.ts', $r024->code_reference);
    }

    #[Test]
    public function idempotence_re_seed_produit_le_meme_etat(): void
    {
        $this->seed(FiscalRulesSeeder::class);
        $countAfterFirst = FiscalRule::query()->count();
        $hashAfterFirst = $this->hashAllRows();

        $this->seed(FiscalRulesSeeder::class);
        $countAfterSecond = FiscalRule::query()->count();
        $hashAfterSecond = $this->hashAllRows();

        self::assertSame($countAfterFirst, $countAfterSecond);
        self::assertSame($hashAfterFirst, $hashAfterSecond);
    }

    #[Test]
    public function reseed_restaure_une_modification_manuelle_de_description(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        FiscalRule::query()
            ->where('rule_code', 'R-2024-002')
            ->where('fiscal_year', 2024)
            ->update(['description' => 'BOGUS DESCRIPTION OVERRIDE']);

        $this->seed(FiscalRulesSeeder::class);

        $restored = FiscalRule::query()
            ->where('rule_code', 'R-2024-002')
            ->where('fiscal_year', 2024)
            ->firstOrFail();
        self::assertNotSame('BOGUS DESCRIPTION OVERRIDE', $restored->description);
        self::assertStringContainsString('Mécanique du prorata journalier', $restored->description);
    }

    #[Test]
    public function mirror_delete_supprime_les_orphelins_de_l_annee_synchronisee(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        FiscalRule::factory()->create([
            'rule_code' => 'R-2024-999',
            'fiscal_year' => 2024,
        ]);

        self::assertSame(25, FiscalRule::query()->where('fiscal_year', 2024)->count());

        $this->seed(FiscalRulesSeeder::class);

        self::assertSame(24, FiscalRule::query()->where('fiscal_year', 2024)->count());
        self::assertFalse(FiscalRule::query()
            ->where('rule_code', 'R-2024-999')
            ->where('fiscal_year', 2024)
            ->exists());
    }

    #[Test]
    public function annee_non_enregistree_dans_le_registry_n_est_pas_touchee(): void
    {
        // Pose une règle pour 2090, année non enregistrée dans le
        // registry de production (config floty.fiscal.year_boots = [2024]).
        FiscalRule::factory()->create([
            'rule_code' => 'R-2090-LEGACY',
            'fiscal_year' => 2090,
        ]);

        $this->seed(FiscalRulesSeeder::class);

        // La règle 2090 doit toujours exister - le mirror ne l'a pas touchée.
        self::assertTrue(FiscalRule::query()
            ->where('rule_code', 'R-2090-LEGACY')
            ->where('fiscal_year', 2090)
            ->exists());
    }

    private function hashAllRows(): string
    {
        $rows = FiscalRule::query()
            ->orderBy('fiscal_year')
            ->orderBy('display_order')
            ->orderBy('rule_code')
            ->get(['rule_code', 'fiscal_year', 'name', 'description', 'rule_type', 'taxes_concerned', 'legal_basis', 'display_order', 'is_active'])
            ->toArray();

        return md5(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
