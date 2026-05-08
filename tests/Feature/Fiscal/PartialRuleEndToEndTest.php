<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\FiscalRule as FiscalRuleModel;
use App\Models\User;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Fiscal\PartialRuleEndToEndStubs\FullYearStub2090;
use Tests\Feature\Fiscal\PartialRuleEndToEndStubs\PartialMidYearStub2090;
use Tests\TestCase;

/**
 * Validation end-to-end de la chaîne de granularité temporelle κ
 * (chantier κ.8).
 *
 * Couvre l'**artefact permanent** du chantier κ.8 : la propagation
 * d'une règle partielle (mid-year) du registry → seeder mirror → DTO
 * → page Inertia. Sans toucher à `app/Fiscal/Year2026/` (le setup de
 * validation visuelle est temporaire et a été nettoyé après la phase).
 *
 * Stratégie : enregistrer **à la volée** dans le registry singleton
 * une année stub (2090) avec 2 règles (full-year + partielle), seeder
 * mirror, GET la page « Règles de calcul » avec `?year=2090` et
 * vérifier les props Inertia.
 */
final class PartialRuleEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const int STUB_YEAR = 2090;

    private FiscalRuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(FiscalRuleRegistry::class);
        $this->app->forgetInstance(RuleEffectiveSegmenter::class);
    }

    #[Test]
    public function regle_partielle_2090_apparait_avec_dates_clippees_et_is_full_year_false_dans_le_dto(): void
    {
        $this->registry->register(self::STUB_YEAR, [
            FullYearStub2090::class,
            PartialMidYearStub2090::class,
        ]);

        $this->seed(FiscalRulesSeeder::class);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/app/fiscal-rules?year='.self::STUB_YEAR);

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/FiscalRules/Index/Index')
                ->has('rules', 2)
                ->where('rules.0.ruleCode', 'R-2090-E2E-FULLYEAR')
                ->where('rules.0.isFullYear', true)
                ->where('rules.0.applicabilityStartInYear', '2090-01-01')
                ->where('rules.0.applicabilityEndInYear', '2090-12-31')
                ->where('rules.1.ruleCode', 'R-2090-E2E-PARTIAL')
                ->where('rules.1.isFullYear', false)
                ->where('rules.1.applicabilityStartInYear', '2090-07-01')
                ->where('rules.1.applicabilityEndInYear', '2090-12-31')
                ->where('selectedYear', self::STUB_YEAR),
            );
    }

    #[Test]
    public function mirror_seeder_supprime_les_lignes_orphelines_apres_retrait_d_une_regle(): void
    {
        // Phase 1 : on enregistre 2 règles, seed → 2 lignes BDD pour 2090.
        $this->registry->register(self::STUB_YEAR, [
            FullYearStub2090::class,
            PartialMidYearStub2090::class,
        ]);
        $this->seed(FiscalRulesSeeder::class);

        self::assertSame(
            2,
            FiscalRuleModel::query()->where('fiscal_year', self::STUB_YEAR)->count(),
            'après le 1er seed, 2 lignes 2090 devraient exister',
        );

        // Phase 2 : on retire la règle partielle du registry et on
        // re-seede. Le mirror doit supprimer la ligne orpheline.
        $this->registry->register(self::STUB_YEAR, [
            FullYearStub2090::class,
        ]);
        $this->seed(FiscalRulesSeeder::class);

        self::assertSame(
            1,
            FiscalRuleModel::query()->where('fiscal_year', self::STUB_YEAR)->count(),
            'après le 2e seed sans la partielle, il ne doit rester que la full-year',
        );
        self::assertFalse(
            FiscalRuleModel::query()
                ->where('fiscal_year', self::STUB_YEAR)
                ->where('rule_code', 'R-2090-E2E-PARTIAL')
                ->exists(),
            'la règle partielle doit avoir été supprimée par le mirror',
        );
    }
}
