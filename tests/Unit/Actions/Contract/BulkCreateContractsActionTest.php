<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BulkCreateContractsActionTest extends TestCase
{
    use RefreshDatabase;

    private BulkCreateContractsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(BulkCreateContractsAction::class);
    }

    #[Test]
    public function cree_n_contrats_pour_n_vehicules_partageant_la_meme_plage(): void
    {
        $company = Company::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();
        $vehicleC = Vehicle::factory()->create();

        $createdIds = $this->action->execute($this->makeData(
            [$vehicleA->id, $vehicleB->id, $vehicleC->id],
            $company->id,
        ));

        $this->assertCount(3, $createdIds);
        $this->assertSame(3, Contract::query()->count());
        $this->assertDatabaseHas('contracts', ['vehicle_id' => $vehicleA->id]);
        $this->assertDatabaseHas('contracts', ['vehicle_id' => $vehicleB->id]);
        $this->assertDatabaseHas('contracts', ['vehicle_id' => $vehicleC->id]);
    }

    #[Test]
    public function rollback_complet_si_un_vehicule_presente_un_overlap(): void
    {
        $company = Company::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();

        Contract::factory()->forVehicle($vehicleB)->forCompany($company)->create([
            'start_date' => '2024-03-10',
            'end_date' => '2024-03-20',
        ]);

        $countBefore = Contract::query()->count();

        try {
            $this->action->execute($this->makeData(
                [$vehicleA->id, $vehicleB->id],
                $company->id,
            ));
            $this->fail('Exception attendue.');
        } catch (ContractOverlapException $e) {
            $this->assertSame($vehicleB->id, $e->vehicleId);
        }

        $this->assertSame($countBefore, Contract::query()->count());
    }

    #[Test]
    public function bulk_create_respecte_un_budget_query_borne_par_constante(): void
    {
        $company = Company::factory()->create();
        $vehicles = collect(range(1, 20))
            ->map(static fn (): Vehicle => Vehicle::factory()->create())
            ->all();
        $vehicleIds = array_map(static fn (Vehicle $v): int => $v->id, $vehicles);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $createdIds = $this->action->execute($this->makeData($vehicleIds, $company->id));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(20, $createdIds);
        // Cap 115 : baseline 101 (1 batch overlap + 20 INSERT + 20×~3 syncDrivers + tx).
        // Marge +14 pour évolutions Observer/middleware sans masquer une régression
        // ré-introduisant le N+1 overlap (≥20 queries).
        self::assertLessThan(
            115,
            $queryCount,
            "Trop de queries SQL ({$queryCount}) pour bulk create de 20 véhicules - possible régression batch overlap.",
        );
    }

    #[Test]
    public function exception_pointe_le_premier_overlap_par_start_date_asc(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Ordre SQL `vehicle_id, start_date` : le plus ancien (2024-02-25) doit ressortir.
        $earliest = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-02-25',
            'end_date' => '2024-03-05',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-10',
            'end_date' => '2024-03-20',
        ]);

        try {
            $this->action->execute($this->makeData([$vehicle->id], $company->id));
            $this->fail('Exception attendue.');
        } catch (ContractOverlapException $e) {
            self::assertSame($vehicle->id, $e->vehicleId);
            self::assertSame($earliest->id, $e->conflictingContractId);
            self::assertSame('2024-02-25', $e->conflictingStartDate);
            self::assertSame('2024-03-05', $e->conflictingEndDate);
        }
    }

    /**
     * @param  list<int>  $vehicleIds
     */
    private function makeData(array $vehicleIds, int $companyId): BulkStoreContractsData
    {
        return new BulkStoreContractsData(
            vehicleIds: $vehicleIds,
            companyId: $companyId,
            driverIds: [],
            startDate: '2024-03-01',
            endDate: '2024-03-15',
            contractReference: null,
            notes: null,
        );
    }
}
