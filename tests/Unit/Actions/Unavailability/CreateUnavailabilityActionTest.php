<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Unavailability;

use App\Actions\Unavailability\CreateUnavailabilityAction;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests isolés de l'orchestration création indispo : décision métier
 * `has_fiscal_impact` (dérivé de `UnavailabilityType::isFiscallyReductive`)
 * + politique de cohabitation avec les contrats (ADR-0019).
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
    public function calcule_has_fiscal_impact_a_true_pour_la_fourriere_publique(): void
    {
        $vehicle = Vehicle::factory()->create();

        $unavailability = $this->action->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::PoundPublic,
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
    public function indispo_chevauchant_un_contrat_existant_est_persistee_sans_blocage(): void
    {
        // ADR-0019 D1 : la politique de cohabitation autorise la
        // saisie d'une indispo dont la plage chevauche un contrat
        // actif. R-2024-008 traite l'intersection au moment du calcul
        // fiscal, pas au moment de l'écriture.
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-05-10',
            'end_date' => '2024-05-15',
        ]);

        $unavailability = $this->action->execute(new StoreUnavailabilityData(
            vehicleId: $vehicle->id,
            type: UnavailabilityType::PoundPublic,
            startDate: '2024-05-12',
            endDate: '2024-05-20',
            description: null,
        ));

        $this->assertDatabaseHas('unavailabilities', [
            'id' => $unavailability->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-12',
            'end_date' => '2024-05-20',
        ]);
        $this->assertTrue($unavailability->has_fiscal_impact);
    }
}
