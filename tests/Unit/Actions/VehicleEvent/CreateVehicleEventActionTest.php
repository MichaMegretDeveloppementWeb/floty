<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\VehicleEvent;

use App\Actions\VehicleEvent\CreateVehicleEventAction;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
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
        $this->action = $this->app->make(CreateVehicleEventAction::class);
    }

    #[Test]
    public function calcule_has_fiscal_impact_a_true_pour_la_fourriere_publique(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::PoundPublic,
            startDate: '2024-03-01',
            endDate: '2024-03-15',
            description: null,
            title: null,
            categories: null,
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function persiste_le_cout_en_centimes(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Maintenance,
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            title: null,
            categories: null,
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
            type: VehicleEventType::Maintenance,
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            title: null,
            categories: null,
        ));

        $this->assertNull($vehicleEvent->fresh()->amount_cents);
    }

    #[Test]
    public function calcule_has_fiscal_impact_a_false_pour_la_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Maintenance,
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            title: null,
            categories: null,
        ));

        $this->assertFalse($vehicleEvent->has_fiscal_impact);
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
            type: VehicleEventType::PoundPublic,
            startDate: '2024-05-12',
            endDate: '2024-05-20',
            description: null,
            title: null,
            categories: null,
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
    public function type_connu_recoit_sa_categorie_par_defaut(): void
    {
        // Un type prédéfini reçoit automatiquement sa catégorie par défaut
        // (stockée comme ligne, plus dérivée à l'affichage).
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Maintenance,
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            title: null,
            categories: null,
        ));

        $this->assertSame(['Entretien'], $vehicleEvent->categories()->pluck('category')->all());
    }

    #[Test]
    public function type_connu_prepend_le_defaut_puis_ajoute_les_categories_custom(): void
    {
        // Le défaut occupe 1 place, l'utilisateur peut en ajouter jusqu'à 4.
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Maintenance,
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
            title: null,
            categories: ['Pneus hiver', 'Révision'],
        ));

        $this->assertSame(
            ['Entretien', 'Pneus hiver', 'Révision'],
            $vehicleEvent->categories()->pluck('category')->all(),
        );
    }

    #[Test]
    public function evenement_autre_persiste_titre_et_categories(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Other,
            startDate: '2024-06-01',
            endDate: '2024-06-02',
            description: null,
            title: 'Pose covering publicitaire',
            categories: ['Marketing', 'Esthétique'],
            impliesUnavailability: true,
        ));

        $this->assertDatabaseHas('vehicle_events', [
            'id' => $vehicleEvent->id,
            'type' => 'other',
            'title' => 'Pose covering publicitaire',
            'has_fiscal_impact' => false,
            'implies_unavailability' => true,
        ]);
        $this->assertSame(
            ['Marketing', 'Esthétique'],
            $vehicleEvent->categories()->pluck('category')->all(),
        );
    }

    #[Test]
    public function categories_dedupliquees_insensible_casse_et_plafonnees_a_cinq(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Other,
            startDate: '2024-06-01',
            endDate: '2024-06-02',
            description: null,
            title: 'Divers',
            categories: ['A', 'a', 'B', 'C', 'D', 'E', 'F'],
        ));

        // 'a' dédupliqué de 'A', puis plafond à 5.
        $this->assertSame(
            ['A', 'B', 'C', 'D', 'E'],
            $vehicleEvent->categories()->pluck('category')->all(),
        );
    }

    #[Test]
    public function evenement_autre_sans_indispo_persiste_implies_false(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Other,
            startDate: '2024-06-01',
            endDate: '2024-06-02',
            description: null,
            title: 'Changement de coordonnées',
            categories: ['Administratif'],
            impliesUnavailability: false,
        ));

        $this->assertFalse($vehicleEvent->implies_unavailability);
        $this->assertFalse($vehicleEvent->has_fiscal_impact);
    }

    #[Test]
    public function type_connu_force_implies_true_et_ignore_le_titre(): void
    {
        // Robustesse : même si le client envoie titre/implies pour un type
        // connu, l'Action normalise (titre null, implies true). Les catégories
        // custom, elles, sont acceptées en plus du défaut.
        $vehicle = Vehicle::factory()->create();

        $vehicleEvent = $this->action->execute(new StoreVehicleEventData(
            vehicleId: $vehicle->id,
            type: VehicleEventType::Maintenance,
            startDate: '2024-06-01',
            endDate: '2024-06-02',
            description: null,
            title: 'devrait etre ignore',
            categories: ['Pneus'],
            impliesUnavailability: false,
        ));

        $this->assertNull($vehicleEvent->title);
        $this->assertTrue($vehicleEvent->implies_unavailability);
        $this->assertSame(['Entretien', 'Pneus'], $vehicleEvent->categories()->pluck('category')->all());
    }
}
