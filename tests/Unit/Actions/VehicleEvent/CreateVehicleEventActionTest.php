<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\VehicleEvent;

use App\Actions\VehicleEvent\CreateVehicleEventAction;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Support\VehicleEvent\EventNatureCatalog;
use Database\Seeders\VehicleEventNatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateVehicleEventActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateVehicleEventAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleEventNatureSeeder::class);
        $this->action = $this->app->make(CreateVehicleEventAction::class);
    }

    #[Test]
    public function une_nature_reductrice_rend_l_evenement_reducteur(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Mise en fourrière',
            startDate: '2024-03-01',
            endDate: '2024-03-15',
            description: null,
            categories: ['Fourrière (demande publique)'],
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function une_nature_reductrice_parmi_d_autres_suffit(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Sinistre grave',
            startDate: '2024-03-01',
            endDate: '2024-03-15',
            description: null,
            categories: ['Carrosserie', EventNatureCatalog::REDUCTIVE[0], 'Expertise'],
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function natures_non_reductrices_et_libres_ne_reduisent_pas(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            categories: ['Entretien', 'Saisie libre'],
        ));

        $this->assertFalse($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function evenement_reducteur_force_l_indisponibilite(): void
    {
        // La checkbox « indisponibilité » est verrouillée côté UI ; le serveur
        // force la même règle même si le payload tente l'inverse.
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Suspension du CI',
            startDate: '2024-03-01',
            endDate: '2024-03-15',
            description: null,
            categories: ["Suspension du certificat d'immatriculation"],
            impliesUnavailability: false,
        ));

        $this->assertTrue($vehicleEvent->implies_unavailability);
        $this->assertTrue($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function evenement_non_reducteur_respecte_le_choix_d_indisponibilite(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Changement de coordonnées',
            startDate: '2024-06-01',
            endDate: '2024-06-02',
            description: null,
            categories: ['Administratif'],
            impliesUnavailability: false,
        ));

        $this->assertFalse($vehicleEvent->implies_unavailability);
        $this->assertFalse($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function persiste_le_cout_en_centimes(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            categories: ['Entretien'],
            amountCents: 123_456,
        ));

        $this->assertSame(123_456, $vehicleEvent->fresh()->amount_cents);
    }

    #[Test]
    public function cout_null_par_defaut(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            categories: ['Entretien'],
        ));

        $this->assertNull($vehicleEvent->fresh()->amount_cents);
    }

    #[Test]
    public function indispo_chevauchant_un_contrat_existant_est_persistee_sans_blocage(): void
    {
        // ADR-0019 D1 : la cohabitation indispo/contrat est autorisée à l'écriture ;
        // R-2024-008 traite l'intersection au moment du calcul fiscal.
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-05-10',
            'end_date' => '2024-05-15',
        ]);

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Mise en fourrière',
            startDate: '2024-05-12',
            endDate: '2024-05-20',
            description: null,
            categories: ['Fourrière (demande publique)'],
        ));

        $this->assertDatabaseHas('vehicle_events', [
            'id' => $vehicleEvent->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-12',
            'end_date' => '2024-05-20',
        ]);
        $this->assertTrue($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function persiste_titre_et_natures_dans_l_ordre(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: '  Pose covering publicitaire  ',
            startDate: '2024-06-01',
            endDate: '2024-06-02',
            description: null,
            categories: ['Marketing', 'Esthétique'],
        ));

        $this->assertDatabaseHas('vehicle_events', [
            'id' => $vehicleEvent->id,
            'title' => 'Pose covering publicitaire',
            'has_fiscal_impact' => false,
        ]);
        $this->assertSame(
            ['Marketing', 'Esthétique'],
            $vehicleEvent->categories()->pluck('category')->all(),
        );
    }

    #[Test]
    public function persiste_les_lignes_de_detail_dans_l_ordre_dedupliquees(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            categories: ['Entretien'],
            details: ['Vidange', '  vidange ', 'Changement courroie', ''],
        ));

        $this->assertSame(
            ['Vidange', 'Changement courroie'],
            $vehicleEvent->details()->pluck('detail')->all(),
        );
    }

    #[Test]
    public function persiste_le_garage_et_le_code_postal_trims(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            categories: ['Entretien'],
            garage: '  Garage Martin  ',
            postalCode: ' 91100 ',
        ));

        $this->assertSame('Garage Martin', $vehicleEvent->garage);
        $this->assertSame('91100', $vehicleEvent->postal_code);
    }

    #[Test]
    public function un_garage_blanc_est_persiste_null(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            categories: ['Entretien'],
            garage: '   ',
            postalCode: null,
        ));

        $this->assertNull($vehicleEvent->garage);
        $this->assertNull($vehicleEvent->postal_code);
    }

    #[Test]
    public function les_details_sont_optionnels(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Entretien courant',
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            categories: ['Entretien'],
        ));

        $this->assertSame(0, $vehicleEvent->details()->count());
    }

    #[Test]
    public function natures_dedupliquees_insensible_casse_sans_plafond(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            title: 'Divers',
            startDate: '2024-06-01',
            endDate: '2024-06-02',
            description: null,
            categories: ['A', 'a', 'B', 'C', 'D', 'E', 'F'],
        ));

        // 'a' dédupliqué de 'A' ; aucune limite de nombre (natures illimitées).
        $this->assertSame(
            ['A', 'B', 'C', 'D', 'E', 'F'],
            $vehicleEvent->categories()->pluck('category')->all(),
        );
    }
}
