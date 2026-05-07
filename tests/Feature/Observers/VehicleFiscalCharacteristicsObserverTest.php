<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleFiscalCharacteristicsObserverTest extends TestCase
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
    }

    #[Test]
    public function created_flag_les_declarations_dont_un_contrat_utilise_le_vehicule(): void
    {
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
            'contract_type' => ContractType::Lcd,
        ]);
        $declaration = $this->makeDeclaration(2025);
        self::assertFalse($declaration->is_obsolete);

        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $this->vehicle->id,
        ]);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::VfcCreated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function updated_flag_les_declarations(): void
    {
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
            'contract_type' => ContractType::Lcd,
        ]);
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $this->vehicle->id,
        ]);
        $declaration = $this->makeDeclaration(2025);

        $vfc->update(['co2_wltp' => 999]);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::VfcUpdated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function n_invalide_pas_si_aucun_contrat_utilise_le_vehicule(): void
    {
        $declaration = $this->makeDeclaration(2025);

        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $this->vehicle->id,
        ]);

        self::assertFalse($declaration->fresh()->is_obsolete);
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
