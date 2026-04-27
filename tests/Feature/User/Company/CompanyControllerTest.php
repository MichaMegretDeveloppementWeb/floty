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
                ->component('User/Companies/Index')
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
                ->component('User/Companies/Create')
                ->has('colors'),
            );
    }

    #[Test]
    public function store_cree_une_entreprise(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/companies', [
                'legal_name' => 'Acme SAS',
                'short_code' => 'ACME',
                'color' => 'indigo',
                'country' => 'FR',
            ])
            ->assertRedirect('/app/companies');

        $this->assertDatabaseHas('companies', [
            'legal_name' => 'Acme SAS',
            'short_code' => 'ACME',
        ]);
    }
}
