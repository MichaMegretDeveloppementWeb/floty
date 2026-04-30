<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Contract;

use App\Actions\Contract\StoreContractAction;
use App\Data\User\Contract\StoreContractData;
use App\Enums\Contract\ContractType;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de l'orchestration création + validation applicative anti-overlap
 * en amont du trigger MySQL (cf. ADR-0014 D5).
 */
final class StoreContractActionTest extends TestCase
{
    use RefreshDatabase;

    private StoreContractAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(StoreContractAction::class);
    }

    #[Test]
    public function cree_un_contrat_quand_aucun_overlap(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $contract = $this->action->execute($this->makeData($vehicle->id, $company->id));

        $this->assertInstanceOf(Contract::class, $contract);
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
            'contract_type' => ContractType::Lcd->value,
        ]);
    }

    #[Test]
    public function refuse_si_la_plage_chevauche_un_contrat_existant(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        $this->expectException(ContractOverlapException::class);

        $this->action->execute($this->makeData(
            $vehicle->id,
            $company->id,
            startDate: '2024-03-10',
            endDate: '2024-03-20',
        ));
    }

    #[Test]
    public function un_contrat_soft_deleted_ne_bloque_plus_la_creation(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $existing = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);
        $existing->delete();

        $contract = $this->action->execute($this->makeData(
            $vehicle->id,
            $company->id,
            startDate: '2024-03-05',
            endDate: '2024-03-20',
        ));

        $this->assertInstanceOf(Contract::class, $contract);
        $this->assertSoftDeleted($existing);
    }

    #[Test]
    public function derive_lld_pour_un_contrat_de_60_jours(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $contract = $this->action->execute($this->makeData(
            $vehicle->id,
            $company->id,
            startDate: '2024-11-01',
            endDate: '2024-12-30',
        ));

        $this->assertSame(ContractType::Lld, $contract->contract_type);
    }

    private function makeData(
        int $vehicleId,
        int $companyId,
        string $startDate = '2024-03-01',
        string $endDate = '2024-03-15',
    ): StoreContractData {
        return new StoreContractData(
            vehicleId: $vehicleId,
            companyId: $companyId,
            driverId: null,
            startDate: $startDate,
            endDate: $endDate,
            contractReference: null,
            notes: null,
        );
    }
}
