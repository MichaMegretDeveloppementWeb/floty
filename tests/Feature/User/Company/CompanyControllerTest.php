<?php

declare(strict_types=1);

namespace Tests\Feature\User\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_liste_les_entreprises(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/app/companies')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Companies/Index/Index')
                ->has('companies', 3),
            );
    }

    #[Test]
    public function create_renvoie_les_couleurs_disponibles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Companies/Create/Index')
                ->has('colors'),
            );
    }

    #[Test]
    public function store_cree_une_entreprise_avec_short_code_auto_genere(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/companies', [
                'legal_name' => 'Acme SAS',
                'color' => 'indigo',
                'country' => 'FR',
            ])
            ->assertRedirect('/app/companies');

        // 'Acme SAS' = 2 mots → 1ère du 1er + 2 premières du 2e = 'ASA'
        $this->assertDatabaseHas('companies', [
            'legal_name' => 'Acme SAS',
            'short_code' => 'ASA',
        ]);
    }

    #[Test]
    public function store_refuse_la_creation_si_le_short_code_genere_collisionne(): void
    {
        $user = User::factory()->create();
        // Pré-existant avec short_code 'ASA' pour forcer la collision
        Company::factory()->create(['legal_name' => 'Pré-existant', 'short_code' => 'ASA']);

        $this->actingAs($user)
            ->post('/app/companies', [
                'legal_name' => 'Acme SAS', // génèrerait 'ASA' → collision
                'color' => 'indigo',
                'country' => 'FR',
            ])
            ->assertSessionHasErrors(['legal_name']);

        $this->assertDatabaseMissing('companies', ['legal_name' => 'Acme SAS']);
    }
}
