<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\VehicleQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie l'orchestration repo + agrégateur fiscal + mapping DTO du
 * service `VehicleQueryService` post-migration vers les Repositories.
 */
final class VehicleQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(VehicleQueryService::class);
    }

    #[Test]
    public function list_for_fleet_view_renvoie_un_dto_par_vehicule_avec_taxe_annuelle(): void
    {
        $year = (int) config('floty.fiscal.current_year');
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        $start = Carbon::create($year, 4, 1);
        for ($i = 0; $i < 40; $i++) {
            Assignment::factory()->create([
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'date' => $start->copy()->addDays($i)->toDateString(),
            ]);
        }

        $result = $this->service->listForFleetView($year);

        $items = $result->toArray();
        self::assertCount(1, $items);
        self::assertSame($vehicle->id, $items[0]['id']);
        self::assertSame($vehicle->license_plate, $items[0]['licensePlate']);
        self::assertGreaterThan(0.0, $items[0]['annualTaxDue']);
    }

    #[Test]
    public function list_for_options_filtre_les_vehicules_sortis(): void
    {
        Vehicle::factory()->create(['exit_date' => null]);
        Vehicle::factory()->create([
            'exit_date' => '2024-01-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);

        $result = $this->service->listForOptions();

        self::assertCount(1, $result->toArray());
    }
}
