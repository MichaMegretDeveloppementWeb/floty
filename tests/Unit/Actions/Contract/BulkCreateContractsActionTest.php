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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de la création multi-contrats en transaction unique. Vérifie
 * que la transaction rollback complètement si l'un des véhicules a un
 * conflit d'overlap (l'utilisateur soumet un lot global ou rien).
 */
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

        // Préposer un contrat sur vehicleB qui chevauchera la plage du
        // bulk → l'ensemble doit être rollback.
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

        // Aucun nouveau contrat n'a été inséré (rollback du transaction).
        $this->assertSame($countBefore, Contract::query()->count());
    }

    /**
     * @param  list<int>  $vehicleIds
     */
    private function makeData(array $vehicleIds, int $companyId): BulkStoreContractsData
    {
        return new BulkStoreContractsData(
            vehicleIds: $vehicleIds,
            companyId: $companyId,
            driverId: null,
            startDate: '2024-03-01',
            endDate: '2024-03-15',
            contractReference: null,
            notes: null,
        );
    }
}
