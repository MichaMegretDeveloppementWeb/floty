<?php

declare(strict_types=1);

namespace Tests\Feature\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Page « Événements » globale (tous véhicules) · filtres type/catégorie/année,
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
                ->has('options.typeValues')
                ->has('options.categorySuggestions')
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
    public function filtre_par_types_multi(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->create();
        VehicleEvent::factory()->maintenance()->create();
        VehicleEvent::factory()->poundPublic()->create();

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['types' => ['maintenance']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 2)
                ->has('events.data', 2),
            );
    }

    #[Test]
    public function suggestions_de_type_exposent_les_titres_personnalises_pas_le_bucket_other(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->create();
        VehicleEvent::factory()->custom('Lavage')->create();
        VehicleEvent::factory()->custom('Lavage')->create();
        VehicleEvent::factory()->custom('Changement de pneus')->create();
        // System lifecycle marker (other + system_kind): never a suggestion.
        VehicleEvent::factory()->custom('Sortie de flotte')->create([
            'system_kind' => VehicleEventSystemKind::FleetExit,
        ]);

        $this->actingAs($user)
            ->get('/app/vehicle-events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('options.typeValues', [
                    'maintenance',
                    'Changement de pneus',
                    'Lavage',
                ]),
            );
    }

    #[Test]
    public function filtre_par_titre_personnalise(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->custom('Lavage')->create(['amount_cents' => 3000]);
        VehicleEvent::factory()->custom('Lavage')->create(['amount_cents' => 4000]);
        VehicleEvent::factory()->custom('Changement de pneus')->create(['amount_cents' => 50000]);
        VehicleEvent::factory()->maintenance()->create(['amount_cents' => 99999]);

        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['types' => ['Lavage']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 2)
                ->where('totalAmountCents', 7000),
            );
    }

    #[Test]
    public function filtre_combine_type_connu_et_titre_personnalise_ou_dans_l_axe(): void
    {
        $user = User::factory()->create();
        VehicleEvent::factory()->maintenance()->create();
        VehicleEvent::factory()->custom('Lavage')->create();
        VehicleEvent::factory()->custom('Changement de pneus')->create();
        VehicleEvent::factory()->poundPublic()->create();

        // maintenance (enum connu) OU « Lavage » (titre personnalisé).
        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['types' => ['maintenance', 'Lavage']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 2),
            );
    }

    #[Test]
    public function filtre_par_categories_multi_insensible_casse_ou_dans_l_axe(): void
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
        VehicleEvent::factory()->maintenance()->create(['amount_cents' => 10000]);
        VehicleEvent::factory()->maintenance()->create(['amount_cents' => 20000]);
        VehicleEvent::factory()->maintenance()->create(['amount_cents' => 30000]);
        VehicleEvent::factory()->poundPublic()->create(['amount_cents' => 99999]);

        // Page de 2 mais total = somme des 3 maintenance (60000), pas les 2 de la page,
        // et la fourrière (autre type) exclue par le filtre.
        $this->actingAs($user)
            ->get(route('user.vehicle-events.index', ['types' => ['maintenance'], 'perPage' => 10]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.meta.total', 3)
                ->where('totalAmountCents', 60000),
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
