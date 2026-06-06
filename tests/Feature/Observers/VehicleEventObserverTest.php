<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleEventObserverTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Vehicle $vehicle;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'contract_type' => ContractType::Lld,
        ]);
    }

    #[Test]
    public function indispo_non_reductrice_n_invalide_pas(): void
    {
        $declaration = $this->makeDeclaration(2025);

        VehicleEvent::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'type' => VehicleEventType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
        ]);

        self::assertFalse($declaration->fresh()->is_obsolete);
    }

    #[Test]
    public function indispo_reductrice_invalide(): void
    {
        $declaration = $this->makeDeclaration(2025);

        VehicleEvent::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'type' => VehicleEventType::PoundPublic,
            'has_fiscal_impact' => true,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
        ]);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::VehicleEventCreated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function deletion_d_indispo_reductrice_invalide(): void
    {
        $vehicleEvent = VehicleEvent::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'type' => VehicleEventType::PoundPublic,
            'has_fiscal_impact' => true,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
        ]);
        $declaration = $this->makeDeclaration(2025);

        $vehicleEvent->delete();

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::VehicleEventDeleted->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    private function makeDeclaration(int $year): FiscalDeclaration
    {
        return FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear($year)
            ->generated()
            ->create();
    }
}
