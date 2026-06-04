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
        ));

        $this->assertTrue($vehicleEvent->has_fiscal_impact);
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
        ));

        $this->assertDatabaseHas('vehicle_events', [
            'id' => $vehicleEvent->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-12',
            'end_date' => '2024-05-20',
        ]);
        $this->assertTrue($vehicleEvent->has_fiscal_impact);
    }
}
