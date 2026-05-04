<?php

declare(strict_types=1);

namespace Tests\Feature\User\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\AssertsPaginatedIndex;
use Tests\TestCase;

final class CompanyControllerTest extends TestCase
{
    use AssertsPaginatedIndex;
    use RefreshDatabase;

    #[Test]
    public function index_liste_les_entreprises(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/app/companies')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $page->component('User/Companies/Index/Index');
                $this->assertPaginatedShape(
                    $page,
                    'companies',
                    expectedDataCount: 3,
                    expectedMeta: ['total' => 3, 'currentPage' => 1, 'perPage' => 20],
                );
            });
    }

    #[Test]
    public function index_paginate_avec_per_page_personnalise(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(25)->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=10')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 10,
                expectedMeta: ['total' => 25, 'lastPage' => 3, 'perPage' => 10],
            ));
    }

    #[Test]
    public function index_navigation_page_2(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(25)->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=10&page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 10,
                expectedMeta: ['currentPage' => 2, 'from' => 11, 'to' => 20],
            ));
    }

    #[Test]
    public function index_per_page_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=33')
            ->assertSessionHasErrors(['perPage']);
    }

    #[Test]
    public function index_sort_key_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        // 'tax' était une sortKey client-side avant ADR-0020 ; elle est
        // volontairement absente de la whitelist server-side car non SQL.
        $this->actingAs($user)
            ->get('/app/companies?sortKey=tax')
            ->assertSessionHasErrors(['sortKey']);
    }

    #[Test]
    public function index_sort_par_short_code_desc(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['short_code' => 'AAA', 'legal_name' => 'A']);
        Company::factory()->create(['short_code' => 'CCC', 'legal_name' => 'C']);
        Company::factory()->create(['short_code' => 'BBB', 'legal_name' => 'B']);

        $this->actingAs($user)
            ->get('/app/companies?sortKey=shortCode&sortDirection=desc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.shortCode', 'CCC')
                ->where('companies.data.1.shortCode', 'BBB')
                ->where('companies.data.2.shortCode', 'AAA'),
            );
    }

    #[Test]
    public function index_filtre_is_active(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['is_active' => true, 'legal_name' => 'Active 1']);
        Company::factory()->create(['is_active' => true, 'legal_name' => 'Active 2']);
        Company::factory()->create(['is_active' => false, 'legal_name' => 'Inactive']);

        $this->actingAs($user)
            ->get('/app/companies?isActive=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 2,
                expectedMeta: ['total' => 2],
            ));

        $this->actingAs($user)
            ->get('/app/companies?isActive=0')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 1,
                expectedMeta: ['total' => 1],
            ));
    }

    #[Test]
    public function index_search_filtre_par_short_code_legal_name_ou_siren(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['legal_name' => 'Acme SAS', 'short_code' => 'ACS', 'siren' => '111222333']);
        Company::factory()->create(['legal_name' => 'Beta Corp', 'short_code' => 'BCO', 'siren' => '444555666']);
        Company::factory()->create(['legal_name' => 'Gamma SARL', 'short_code' => 'GSA', 'siren' => '777888999']);

        // Search par legal_name
        $this->actingAs($user)
            ->get('/app/companies?search=Acme')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.legalName', 'Acme SAS'),
            );

        // Search par short_code
        $this->actingAs($user)
            ->get('/app/companies?search=BCO')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.legalName', 'Beta Corp'),
            );

        // Search par siren partiel
        $this->actingAs($user)
            ->get('/app/companies?search=777')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.legalName', 'Gamma SARL'),
            );
    }

    #[Test]
    public function index_query_dto_est_renvoye_au_frontend(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=50&sortKey=legalName&sortDirection=desc&search=foo&isActive=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('query.perPage', 50)
                ->where('query.sortKey', 'legalName')
                ->where('query.sortDirection', 'desc')
                ->where('query.search', 'foo')
                ->where('query.isActive', true),
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
