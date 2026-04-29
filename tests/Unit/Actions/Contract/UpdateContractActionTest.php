<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Contract;

use App\Actions\Contract\UpdateContractAction;
use App\Data\User\Contract\UpdateContractData;
use App\Enums\Contract\ContractType;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de la mise à jour d'un contrat — vérifie l'exclusion de la
 * ligne courante dans la recherche d'overlap (un contrat ne se
 * chevauche pas avec lui-même).
 */
final class UpdateContractActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateContractAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(UpdateContractAction::class);
    }

    #[Test]
    public function met_a_jour_les_bornes_quand_aucun_conflit(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $contract = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        $updated = $this->action->execute(
            $contract->id,
            $this->makeData($vehicle->id, $company->id, '2024-03-05', '2024-03-25'),
        );

        $this->assertSame('2024-03-05', $updated->start_date->toDateString());
        $this->assertSame('2024-03-25', $updated->end_date->toDateString());
    }

    #[Test]
    public function exclut_la_ligne_courante_de_la_detection_d_overlap(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $contract = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        // La nouvelle plage chevauche la ligne courante elle-même ; ce
        // n'est pas un conflit, l'update doit passer.
        $updated = $this->action->execute(
            $contract->id,
            $this->makeData($vehicle->id, $company->id, '2024-03-05', '2024-03-20'),
        );

        $this->assertSame('2024-03-05', $updated->start_date->toDateString());
    }

    #[Test]
    public function refuse_si_la_nouvelle_plage_chevauche_un_autre_contrat(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $other = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-04-01',
            'end_date' => '2024-04-30',
        ]);

        $contract = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        $this->expectException(ContractOverlapException::class);

        $this->action->execute(
            $contract->id,
            $this->makeData($vehicle->id, $company->id, '2024-03-15', '2024-04-15'),
        );

        $this->assertNotNull($other);
    }

    private function makeData(
        int $vehicleId,
        int $companyId,
        string $startDate,
        string $endDate,
    ): UpdateContractData {
        return new UpdateContractData(
            vehicleId: $vehicleId,
            companyId: $companyId,
            driverId: null,
            startDate: $startDate,
            endDate: $endDate,
            contractReference: null,
            contractType: ContractType::Lcd,
            notes: null,
        );
    }
}
