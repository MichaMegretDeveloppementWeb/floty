<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\User\Vehicle;

use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les optimisations N+1 du repo VFC, notamment la lecture sur
 * relation préchargée vs query SQL fallback.
 */
final class VehicleFiscalCharacteristicsReadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private VehicleFiscalCharacteristicsReadRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new VehicleFiscalCharacteristicsReadRepository;
    }

    #[Test]
    public function find_current_for_vehicle_utilise_la_relation_prechargee_sans_query(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        // Recharge le véhicule avec la relation eager-loadée.
        $vehicleWithFiscal = Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->find($vehicle->id);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $vfc = $this->repo->findCurrentForVehicle($vehicleWithFiscal);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertNotNull($vfc);
        self::assertSame(0, count($queries), 'Aucune query attendue quand la relation est préchargée');
    }

    #[Test]
    public function find_current_for_vehicle_declenche_une_query_si_la_relation_nest_pas_chargee(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        // Recharge sans eager-load.
        $vehicleWithoutFiscal = Vehicle::query()->find($vehicle->id);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $vfc = $this->repo->findCurrentForVehicle($vehicleWithoutFiscal);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertNotNull($vfc);
        self::assertSame(1, count($queries), '1 query attendue en fallback DB');
    }

    #[Test]
    public function find_current_for_vehicle_renvoie_la_plus_recente_quand_relation_prechargee_avec_historique(): void
    {
        $vehicle = Vehicle::factory()->create();
        // VFC ancienne (close)
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);
        // VFC courante (la plus récente, effective_to null)
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $vehicleWithHistory = Vehicle::query()
            ->with('fiscalCharacteristics')
            ->find($vehicle->id);

        $result = $this->repo->findCurrentForVehicle($vehicleWithHistory);

        self::assertNotNull($result);
        self::assertSame($current->id, $result->id);
    }
}
