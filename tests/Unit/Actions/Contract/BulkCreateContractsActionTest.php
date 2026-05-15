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

    // ---------- Lot 3 D01 · batch overlap-check (1 query au lieu de N) ----------

    /**
     * Lot 3 D01 · garantit que le bulk create utilise un budget query
     * borné par une constante (pas N+1 par véhicule). Avant le refactor,
     * on observait `N appels findOverlapping = N queries SELECT` pour
     * N véhicules. Après · 1 seule query batch via
     * `findAllOverlappingForVehicles`.
     */
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
        // Cap raisonnable Lot 3 D01 · baseline mesurée 101 queries pour
        // 20 véhicules · décomposition · 1 batch overlap + 20 INSERT
        // contract (Eloquent create) + 20×~3 queries syncDrivers (findOrFail
        // + sync DELETE + sync INSERT) + transaction overhead. Le batch
        // overlap a éliminé les 20 SELECT findOverlapping individuels
        // (gain ~19 queries). Cap à 115 · marge +14 sur baseline pour
        // évolutions Observer/middleware sans masquer une régression
        // ré-introduisant le N+1 overlap (qui ferait sauter ≥20 queries).
        // Dette résiduelle hors-D01 · `syncDrivers` × N (3N queries pivot)
        // pourrait être factorisé en 1 INSERT multi-rows séparément.
        self::assertLessThan(
            115,
            $queryCount,
            "Trop de queries SQL ({$queryCount}) pour bulk create de 20 véhicules - possible régression batch overlap.",
        );
    }

    /**
     * Lot 3 D01 · équivalence sémantique fail-fast · si un véhicule
     * présente plusieurs overlaps existants, l'exception doit pointer le
     * **premier** par start_date ASC (déterministe, cohérent avec l'ordre
     * SQL `vehicle_id, start_date` du repo). Garantit qu'on ne casse pas
     * l'UX "le 1er conflit rencontré" au passage du refactor.
     */
    #[Test]
    public function exception_pointe_le_premier_overlap_par_start_date_asc(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // 2 contrats existants sur le même véhicule, tous deux chevauchent
        // la plage du bulk `[2024-03-01, 2024-03-15]`. L'ordre SQL doit
        // sélectionner le plus ancien (start_date 2024-02-25).
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
