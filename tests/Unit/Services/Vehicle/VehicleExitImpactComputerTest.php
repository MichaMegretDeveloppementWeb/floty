<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleExitImpactComputer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la logique d'identification des conflits qui débordent une
 * date de sortie de flotte proposée (cf. ADR-0018 § 8.1).
 *
 * Règle : un contrat ou indispo dont `end_date > exitDate` est en
 * conflit. L'égalité (`end_date == exitDate`) n'est PAS un conflit
 * (le véhicule est utilisable jusqu'à `exit_date` inclus).
 */
final class VehicleExitImpactComputerTest extends TestCase
{
    use RefreshDatabase;

    private VehicleExitImpactComputer $computer;

    private int $vehicleId;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->computer = $this->app->make(VehicleExitImpactComputer::class);
        $this->vehicleId = Vehicle::factory()->create(['exit_date' => null])->id;
        $this->companyId = Company::factory()->create()->id;
    }

    #[Test]
    public function aucun_contrat_ni_indispo_pas_de_conflit(): void
    {
        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertFalse($impact->hasConflicts);
        self::assertSame([], $impact->conflictingContracts);
        self::assertSame([], $impact->conflictingUnavailabilities);
    }

    #[Test]
    public function contrat_qui_finit_avant_exit_date_pas_de_conflit(): void
    {
        $this->createContract('2025-05-01', '2025-06-10');

        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertFalse($impact->hasConflicts);
    }

    #[Test]
    public function contrat_qui_finit_pile_sur_exit_date_pas_de_conflit(): void
    {
        // Bordure : end == exitDate → autorisé (utilisable jusqu'à
        // exit_date inclus).
        $this->createContract('2025-05-01', '2025-06-15');

        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertFalse($impact->hasConflicts);
    }

    #[Test]
    public function contrat_qui_deborde_exit_date_remonte_un_conflit(): void
    {
        $contract = $this->createContract('2025-05-01', '2025-07-31');

        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertTrue($impact->hasConflicts);
        self::assertCount(1, $impact->conflictingContracts);
        self::assertSame($contract->id, $impact->conflictingContracts[0]->id);
        self::assertSame('2025-05-01', $impact->conflictingContracts[0]->startDate);
        self::assertSame('2025-07-31', $impact->conflictingContracts[0]->endDate);
    }

    #[Test]
    public function indispo_qui_deborde_exit_date_remonte_un_conflit(): void
    {
        $unavailability = Unavailability::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2025-06-10',
            'end_date' => '2025-07-05',
        ]);

        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertTrue($impact->hasConflicts);
        self::assertCount(1, $impact->conflictingUnavailabilities);
        self::assertSame($unavailability->id, $impact->conflictingUnavailabilities[0]->id);
        self::assertSame(UnavailabilityType::Maintenance, $impact->conflictingUnavailabilities[0]->type);
    }

    #[Test]
    public function indispo_en_cours_sans_date_de_fin_compte_comme_conflit(): void
    {
        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $this->vehicleId,
            'start_date' => '2025-06-10',
            'end_date' => null,
        ]);

        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertTrue($impact->hasConflicts);
        self::assertCount(1, $impact->conflictingUnavailabilities);
        self::assertSame('9999-12-31', $impact->conflictingUnavailabilities[0]->endDate);
    }

    #[Test]
    public function plusieurs_conflits_mixtes_remontent_tous(): void
    {
        $this->createContract('2025-05-01', '2025-07-31');
        $this->createContract('2025-08-01', '2025-09-30');
        Unavailability::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2025-07-15',
            'end_date' => '2025-07-20',
        ]);

        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertTrue($impact->hasConflicts);
        self::assertCount(2, $impact->conflictingContracts);
        self::assertCount(1, $impact->conflictingUnavailabilities);
    }

    #[Test]
    public function contrat_d_un_autre_vehicule_n_apparait_pas(): void
    {
        $otherVehicleId = Vehicle::factory()->create()->id;
        Contract::factory()->create([
            'vehicle_id' => $otherVehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2025-05-01',
            'end_date' => '2025-07-31',
        ]);

        $impact = $this->computer->computeImpact($this->vehicleId, '2025-06-15');

        self::assertFalse($impact->hasConflicts);
    }

    private function createContract(string $start, string $end): Contract
    {
        return Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $this->companyId,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }
}
