<?php

declare(strict_types=1);

namespace Tests\Feature\User\FiscalRule;

use App\Models\User;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FiscalRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_liste_les_32_regles_2024_avec_tabs_organisees(): void
    {
        // Phase 13 D5.13 · ADR-0022 v1.3 · le contrôleur projette
        // directement depuis le registry (classes PHP). Pas besoin de
        // factory · le seeder alimente la table-index (la query
        // batch des id en a besoin), puis le service lit les 32
        // classes 2024 réelles (18 calcul + 14 informatives).
        $this->seed(FiscalRulesSeeder::class);

        $user = User::factory()->create();

        // `?year=2024` explicite · le fallback du controller pointe
        // sur la dernière année enregistrée (`max($available)`) qui
        // peut varier (2024 → 2026 selon ajouts au registry).
        $this->actingAs($user)
            ->get('/app/fiscal-rules?year=2024')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/FiscalRules/Index/Index')
                ->has('rules', 32)
                ->has('tabs', 3)
                ->where('tabs.0.value', 'calcul')
                ->where('tabs.1.value', 'cadre')
                ->where('tabs.2.value', 'hors-perimetre')
                ->where('selectedYear', 2024),
            );
    }
}
