<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Unavailability;

use App\Actions\Unavailability\CreateUnavailabilityAction;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
use App\Exceptions\Unavailability\UnavailabilityOverlapsAssignmentsException;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests isolés de l'orchestration création indispo : décision métier
 * `has_fiscal_impact` + sécurité overlap.
 */
final class CreateUnavailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateUnavailabilityAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(CreateUnavailabilityAction::class);
    }

    #[Test]
    public function calcule_has_fiscal_impact_a_true_pour_la_fourriere(): void
    {
        $vehicle = Vehicle::factory()->create();

        $unavailability = $this->action->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::Pound,
            startDate: '2024-03-01',
            endDate: '2024-03-15',
            description: null,
        ));

        $this->assertTrue($unavailability->has_fiscal_impact);
    }

    #[Test]
    public function calcule_has_fiscal_impact_a_false_pour_la_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();

        $unavailability = $this->action->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::Maintenance,
            startDate: '2024-04-01',
            endDate: '2024-04-03',
            description: null,
        ));

        $this->assertFalse($unavailability->has_fiscal_impact);
    }

    #[Test]
    public function leve_l_exception_metier_si_la_plage_chevauche_une_attribution(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'date' => '2024-05-12',
        ]);

        $this->expectException(UnavailabilityOverlapsAssignmentsException::class);

        $this->action->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::Maintenance,
            startDate: '2024-05-10',
            endDate: '2024-05-15',
            description: null,
        ));
    }

    #[Test]
    public function ne_persiste_pas_l_indispo_si_overlap(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'date' => '2024-05-12',
        ]);

        try {
            $this->action->execute(new StoreUnavailabilityData(
                vehicleId: $vehicle->id,
                type: UnavailabilityType::Maintenance,
                startDate: '2024-05-10',
                endDate: '2024-05-15',
                description: null,
            ));
            $this->fail('Exception attendue.');
        } catch (UnavailabilityOverlapsAssignmentsException) {
            // OK
        }

        $this->assertDatabaseMissing('unavailabilities', [
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-10',
        ]);
    }

    #[Test]
    public function indispo_en_cours_bloque_les_attributions_futures(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'date' => '2024-09-01',
        ]);

        $this->expectException(UnavailabilityOverlapsAssignmentsException::class);

        $this->action->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::Maintenance,
            startDate: '2024-08-15',
            endDate: null,
            description: 'Pas de date de retour connue',
        ));
    }
}
