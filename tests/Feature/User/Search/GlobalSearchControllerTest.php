<?php

declare(strict_types=1);

namespace Tests\Feature\User\Search;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\FiscalDeclaration;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de l'endpoint AJAX de la palette de recherche globale ⌘K (V1.1).
 *
 * Couvre · validation (q requis · min 2 caractères), recherche par
 * concat marque + modèle + plaque, recherche entreprise (nom + SIREN),
 * recherche conducteur, raccourci contrats croisés (≥ 2 tokens), et
 * recherche de déclaration par entreprise + année.
 */
final class GlobalSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function endpoint_refuse_acces_si_non_authentifie(): void
    {
        // getJson() · le handler d'exception Laravel répond 401
        // Unauthorized pour les requêtes XHR / JSON (au lieu d'un
        // redirect 302 vers /login pour les requests HTML).
        $this->getJson('/app/search?q=test')
            ->assertStatus(401);
    }

    #[Test]
    public function endpoint_422_si_query_trop_courte(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/app/search?q=a')
            ->assertStatus(422);
    }

    #[Test]
    public function endpoint_422_si_query_absente(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/app/search')
            ->assertStatus(422);
    }

    #[Test]
    public function recherche_vehicule_par_marque_seule(): void
    {
        $user = User::factory()->create();

        $renaultClio = Vehicle::factory()->create([
            'brand' => 'Renault',
            'model' => 'Clio',
            'license_plate' => 'AB-123-CD',
        ]);
        $peugeot208 = Vehicle::factory()->create([
            'brand' => 'Peugeot',
            'model' => '208',
            'license_plate' => 'EF-456-GH',
        ]);

        $response = $this->actingAs($user)->getJson('/app/search?q=renault');

        $response->assertOk()
            ->assertJsonCount(1, 'vehicles')
            ->assertJsonPath('vehicles.0.id', $renaultClio->id);

        $this->assertNotContains(
            $peugeot208->id,
            array_column($response->json('vehicles'), 'id'),
        );
    }

    #[Test]
    public function recherche_vehicule_par_marque_et_modele_croises(): void
    {
        $user = User::factory()->create();

        $renaultClio = Vehicle::factory()->create([
            'brand' => 'Renault',
            'model' => 'Clio',
            'license_plate' => 'AB-123-CD',
        ]);
        Vehicle::factory()->create([
            'brand' => 'Peugeot',
            'model' => 'Clio',  // edge case · même modèle, marque différente
            'license_plate' => 'EF-456-GH',
        ]);
        Vehicle::factory()->create([
            'brand' => 'Renault',
            'model' => 'Megane',  // edge case · même marque, modèle différent
            'license_plate' => 'IJ-789-KL',
        ]);

        // « renault clio » · AND strict · seul le 1er véhicule matche
        $this->actingAs($user)
            ->getJson('/app/search?q=renault%20clio')
            ->assertOk()
            ->assertJsonCount(1, 'vehicles')
            ->assertJsonPath('vehicles.0.id', $renaultClio->id);
    }

    #[Test]
    public function recherche_vehicule_par_plaque(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Renault',
            'model' => 'Clio',
            'license_plate' => 'AB-123-CD',
        ]);

        $this->actingAs($user)
            ->getJson('/app/search?q=AB-123')
            ->assertOk()
            ->assertJsonCount(1, 'vehicles')
            ->assertJsonPath('vehicles.0.id', $vehicle->id);
    }

    #[Test]
    public function recherche_entreprise_par_nom(): void
    {
        $user = User::factory()->create();

        $acme = Company::factory()->create(['legal_name' => 'ACME SARL', 'siren' => '123456789']);
        Company::factory()->create(['legal_name' => 'BetaCorp', 'siren' => '987654321']);

        $this->actingAs($user)
            ->getJson('/app/search?q=acme')
            ->assertOk()
            ->assertJsonCount(1, 'companies')
            ->assertJsonPath('companies.0.id', $acme->id)
            ->assertJsonPath('companies.0.sublabel', 'SIREN 123 456 789');
    }

    #[Test]
    public function recherche_entreprise_par_siren(): void
    {
        $user = User::factory()->create();

        $acme = Company::factory()->create(['legal_name' => 'ACME SARL', 'siren' => '123456789']);

        $this->actingAs($user)
            ->getJson('/app/search?q=123456')
            ->assertOk()
            ->assertJsonCount(1, 'companies')
            ->assertJsonPath('companies.0.id', $acme->id);
    }

    #[Test]
    public function recherche_conducteur_par_nom_complet(): void
    {
        $user = User::factory()->create();

        $jean = Driver::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        Driver::factory()->create(['first_name' => 'Paul', 'last_name' => 'Martin']);

        $this->actingAs($user)
            ->getJson('/app/search?q=jean%20dupont')
            ->assertOk()
            ->assertJsonCount(1, 'drivers')
            ->assertJsonPath('drivers.0.id', $jean->id)
            ->assertJsonPath('drivers.0.label', 'Jean Dupont');
    }

    #[Test]
    public function raccourci_contrats_actif_si_au_moins_2_tokens_croises(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Renault',
            'model' => 'Clio',
            'license_plate' => 'AB-123-CD',
        ]);
        $acme = Company::factory()->create(['legal_name' => 'ACME SARL']);

        // 3 contrats pour le couple (vehicle, ACME) · dates échelonnées
        // pour éviter le trigger BDD `overlapping period for this
        // vehicle` (cf. migration 2026_04_24_190005_*).
        Contract::factory()->lcd()->forVehicle($vehicle)->forCompany($acme)->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-03-31',
        ]);
        Contract::factory()->lcd()->forVehicle($vehicle)->forCompany($acme)->create([
            'start_date' => '2025-04-01',
            'end_date' => '2025-06-30',
        ]);
        Contract::factory()->lcd()->forVehicle($vehicle)->forCompany($acme)->create([
            'start_date' => '2025-07-01',
            'end_date' => '2025-09-30',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/app/search?q=renault%20acme')
            ->assertOk()
            ->assertJsonCount(1, 'contractShortcuts')
            ->assertJsonPath('contractShortcuts.0.vehicleId', $vehicle->id)
            ->assertJsonPath('contractShortcuts.0.companyId', $acme->id)
            ->assertJsonPath('contractShortcuts.0.count', 3)
            ->assertJsonPath('contractShortcuts.0.sublabel', '3 contrats');

        $href = $response->json('contractShortcuts.0.href');
        $this->assertStringContainsString('/app/contracts', $href);
        $this->assertStringContainsString('vehicleId='.$vehicle->id, $href);
        $this->assertStringContainsString('companyId='.$acme->id, $href);
    }

    #[Test]
    public function pas_de_raccourci_contrat_si_un_seul_token(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::factory()->create(['brand' => 'Renault', 'model' => 'Clio', 'license_plate' => 'AB-123-CD']);
        $acme = Company::factory()->create(['legal_name' => 'ACME']);
        Contract::factory()->lcd()->forVehicle($vehicle)->forCompany($acme)->create();

        $this->actingAs($user)
            ->getJson('/app/search?q=renault')
            ->assertOk()
            ->assertJsonCount(0, 'contractShortcuts');
    }

    #[Test]
    public function recherche_declaration_par_entreprise_et_annee(): void
    {
        $user = User::factory()->create();

        $acme = Company::factory()->create(['legal_name' => 'ACME SARL']);
        $beta = Company::factory()->create(['legal_name' => 'BetaCorp']);

        $declaration2025 = FiscalDeclaration::factory()
            ->forCompany($acme)
            ->forYear(2025)
            ->generated()
            ->create();
        FiscalDeclaration::factory()
            ->forCompany($beta)
            ->forYear(2025)
            ->generated()
            ->create();
        FiscalDeclaration::factory()
            ->forCompany($acme)
            ->forYear(2024)
            ->generated()
            ->create();

        $this->actingAs($user)
            ->getJson('/app/search?q=acme%202025')
            ->assertOk()
            ->assertJsonCount(1, 'declarations')
            ->assertJsonPath('declarations.0.id', $declaration2025->id);
    }

    #[Test]
    public function declaration_obsolete_exclue_de_la_recherche(): void
    {
        $user = User::factory()->create();

        $acme = Company::factory()->create(['legal_name' => 'ACME SARL']);

        FiscalDeclaration::factory()
            ->forCompany($acme)
            ->forYear(2025)
            ->generated()
            ->create(['is_obsolete' => true]);

        $this->actingAs($user)
            ->getJson('/app/search?q=acme%202025')
            ->assertOk()
            ->assertJsonCount(0, 'declarations');
    }

    #[Test]
    public function pas_de_declaration_si_annee_absente_de_la_query(): void
    {
        $user = User::factory()->create();

        $acme = Company::factory()->create(['legal_name' => 'ACME']);
        FiscalDeclaration::factory()->forCompany($acme)->forYear(2025)->generated()->create();

        $this->actingAs($user)
            ->getJson('/app/search?q=acme')
            ->assertOk()
            ->assertJsonCount(0, 'declarations');
    }

    #[Test]
    public function payload_complet_contient_les_5_groupes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/app/search?q=zzznoresult')
            ->assertOk()
            ->assertJsonStructure([
                'query',
                'vehicles',
                'companies',
                'drivers',
                'contractShortcuts',
                'declarations',
            ]);
    }
}
