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
    public function index_liste_les_24_regles_2024_avec_tabs_organisees(): void
    {
        // Phase 13 D5.13 · ADR-0022 v1.3 · le contrôleur projette
        // directement depuis le registry (classes PHP). Pas besoin de
        // factory · le seeder alimente la table-index (la query
        // batch des id en a besoin), puis le service lit les 24
        // classes 2024 réelles.
        $this->seed(FiscalRulesSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/fiscal-rules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/FiscalRules/Index/Index')
                ->has('rules', 24)
                ->has('tabs', 2)
                ->where('tabs.0.value', 'calcul')
                ->where('tabs.1.value', 'cadre')
                ->where('selectedYear', 2024),
            );
    }
}
