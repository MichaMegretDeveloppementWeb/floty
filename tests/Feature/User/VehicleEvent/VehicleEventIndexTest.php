<?php

declare(strict_types=1);

namespace Tests\Feature\User\VehicleEvent;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Page « Événements » globale (tous véhicules) · filtres nature/année,
 * total des coûts du jeu filtré, pagination, tri, garde-fou budget de requêtes.
 */
final class VehicleEventIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function rend_la_liste_globale_paginee(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/app/vehicle-events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/VehicleEvents/Index/Index')
                ->where('hasAnyVehicleEvent', true)
                ->has('events.data', 3)
                ->has('events.meta')
                ->has('options.natureValues')
                ->has('options.availableYears'),
            );
    }

    #[Test]
    public function placeholder_quand_aucun_evenement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicle-events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasAnyVehicleEvent', false)
                ->has('events.data', 0),
            );
    }

    #[Test]
    public function filtre_par_natures_multi(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create();
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create();
        VehicleEvent::factory()->poundPublic()->withCategories('Fourrière (demande publique)')->create();

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['categories' => ['Entretien']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 2)
                ->has('events.data', 2),
            );
    }

    #[Test]
    public function nature_values_expose_les_natures_presentes_distinctes_et_triees(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create();
        VehicleEvent::factory()->custom('Lavage')->withCategories('Esthétique')->create();
        // Nature dupliquée sur deux événements : exposée une seule fois.
        VehicleEvent::factory()->custom('Lavage')->withCategories('Esthétique')->create();
        VehicleEvent::factory()->custom('Changement de pneus')->withCategories('Pneumatiques')->create();

        $this->actingAs($user)
            ->get('/app/vehicle-events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('options.natureValues', [
                    'Entretien',
                    'Esthétique',
                    'Pneumatiques',
                ]),
            );
    }

    #[Test]
    public function filtre_par_nature_libre(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->custom('Lavage')->withCategories('Esthétique')->create(['amount_cents' => 3000]);
        VehicleEvent::factory()->custom('Lavage')->withCategories('Esthétique')->create(['amount_cents' => 4000]);
        VehicleEvent::factory()->custom('Changement de pneus')->withCategories('Pneumatiques')->create(['amount_cents' => 50000]);
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create(['amount_cents' => 99999]);

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['categories' => ['Esthétique']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 2)
                ->where('totalAmountCents', 7000),
            );
    }

    #[Test]
    public function filtre_combine_nature_catalogue_et_nature_libre_ou_dans_l_axe(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create();
        VehicleEvent::factory()->custom('Lavage')->withCategories('Esthétique')->create();
        VehicleEvent::factory()->custom('Changement de pneus')->withCategories('Pneumatiques')->create();
        VehicleEvent::factory()->poundPublic()->withCategories('Fourrière (demande publique)')->create();

        // « Entretien » (catalogue) OU « Esthétique » (nature libre).
        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['categories' => ['Entretien', 'Esthétique']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 2),
            );
    }

    #[Test]
    public function filtre_par_natures_multi_insensible_casse_ou_dans_l_axe(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create();
        VehicleEvent::factory()->custom()->withCategories('Contrôle')->create();
        VehicleEvent::factory()->custom()->withCategories('Vol')->create();

        // OU dans l'axe (Entretien OU Contrôle), insensible à la casse.
        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['categories' => ['entretien', 'contrôle']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 2),
            );
    }

    #[Test]
    public function recherche_par_nom_de_garage(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->create(['garage' => 'Garage Martin']);
        VehicleEvent::factory()->maintenance()->create(['garage' => 'Carrosserie Dupont']);
        VehicleEvent::factory()->maintenance()->create();

        $this->actingAs($user)
            ->get('/app/vehicle-events?search=martin')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 1)
                ->where('events.data.0.garage', 'Garage Martin'),
            );
    }

    #[Test]
    public function recherche_par_code_postal_partiel(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->create(['postal_code' => '91100']);
        VehicleEvent::factory()->maintenance()->create(['postal_code' => '75011']);
        VehicleEvent::factory()->maintenance()->create();

        $this->actingAs($user)
            ->get('/app/vehicle-events?search=91')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 1)
                ->where('events.data.0.postalCode', '91100'),
            );
    }

    #[Test]
    public function filtre_year_par_date_de_debut(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->create(['start_date' => '2024-06-15', 'end_date' => '2024-06-15']);
        VehicleEvent::factory()->create(['start_date' => '2025-03-10', 'end_date' => '2025-03-10']);

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['year' => 2025]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 1),
            );
    }

    #[Test]
    public function total_amount_porte_sur_le_jeu_filtre_complet_pas_seulement_la_page(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create(['amount_cents' => 10000]);
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create(['amount_cents' => 20000]);
        VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create(['amount_cents' => 30000]);
        VehicleEvent::factory()->poundPublic()->withCategories('Fourrière (demande publique)')->create(['amount_cents' => 99999]);

        // Total = somme des 3 maintenance (60000), pas seulement la page,
        // et la fourrière (autre nature) exclue par le filtre.
        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['categories' => ['Entretien'], 'perPage' => 10]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 3)
                ->where('totalAmountCents', 60000),
            );
    }

    #[Test]
    public function pagination_du_jeu_filtre_par_nature(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            VehicleEvent::factory()->maintenance()->withCategories('Entretien')->create();
        }

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', [
                'categories' => ['Entretien'],
                'perPage' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 12)
                ->where('events.meta.currentPage', 2)
                ->has('events.data', 2),
            );
    }

    #[Test]
    public function tri_par_montant_descendant(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->create(['amount_cents' => 5000]);
        VehicleEvent::factory()->maintenance()->create(['amount_cents' => 90000]);
        VehicleEvent::factory()->maintenance()->create(['amount_cents' => 40000]);

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['sortKey' => 'amount', 'sortDirection' => 'desc']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.data.0.amountCents', 90000)
                ->where('events.data.1.amountCents', 40000)
                ->where('events.data.2.amountCents', 5000),
            );
    }

    #[Test]
    public function tri_par_titre_ascendant(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->custom('Lavage')->create();
        VehicleEvent::factory()->custom('Audit')->create();
        VehicleEvent::factory()->custom('Pneus')->create();

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['sortKey' => 'title', 'sortDirection' => 'asc']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.data.0.title', 'Audit')
                ->where('events.data.1.title', 'Lavage')
                ->where('events.data.2.title', 'Pneus'),
            );
    }

    #[Test]
    public function sort_key_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['sortKey' => 'description']))
            ->assertSessionHasErrors('sortKey');
    }

    #[Test]
    public function budget_query_borne_independant_du_nombre_d_evenements(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            VehicleEvent::factory()->withCategories('Entretien')->create();
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($user)
            ->get('/app/vehicle-events')
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Eager vehicle + categories, total SUM, distincts, exists · borné et
        // indépendant du nombre d'événements (pas de N+1).
        self::assertLessThan(
            20,
            $queryCount,
            "Trop de requêtes ({$queryCount}) sur l'index Événements · possible N+1.",
        );
    }

    #[Test]
    public function chaque_ligne_porte_le_vehicule_et_le_montant(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'EV-001-TT']);
        VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'amount_cents' => 12345,
        ]);

        $this->actingAs($user)
            ->get('/app/vehicle-events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('events.data.0', fn (AssertableInertia $row) => $row
                    ->where('vehicleLicensePlate', 'EV-001-TT')
                    ->where('amountCents', 12345)
                    ->etc()),
            );
    }
}
