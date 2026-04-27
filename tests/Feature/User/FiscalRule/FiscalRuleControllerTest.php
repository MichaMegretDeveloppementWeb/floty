<?php

declare(strict_types=1);

namespace Tests\Feature\User\FiscalRule;

use App\Models\FiscalRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FiscalRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_liste_les_regles_pour_l_annee_courante(): void
    {
        $user = User::factory()->create();
        $year = (int) config('floty.fiscal.available_years')[0];

        FiscalRule::factory()->count(3)->create(['fiscal_year' => $year]);
        // Une règle d'une autre année — ne doit pas apparaître
        FiscalRule::factory()->create(['fiscal_year' => $year - 1]);

        $this->actingAs($user)
            ->get('/app/fiscal-rules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/FiscalRules/Index/Index')
                ->has('rules', 3),
            );
    }
}
