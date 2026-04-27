<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Planning;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Planning\PlanningHeatmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlanningHeatmapServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanningHeatmapService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PlanningHeatmapService::class);
    }

    #[Test]
    public function build_heatmap_construit_la_matrice_52_semaines(): void
    {
        $year = (int) config('floty.fiscal.current_year');
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Une attribution en semaine 10
        $weekStart = Carbon::now()->setISODate($year, 10)->startOfWeek();
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'date' => $weekStart->toDateString(),
        ]);

        $payload = $this->service->buildHeatmap($year);

        $vehicles = $payload['vehicles']->toArray();
        self::assertCount(1, $vehicles);
        self::assertCount(52, $vehicles[0]['weeks']);
        self::assertSame(1, $vehicles[0]['weeks'][9]); // semaine 10 indexée [9]
        self::assertSame(1, $vehicles[0]['daysTotal']);

        $companies = $payload['companies']->toArray();
        self::assertCount(1, $companies);
    }
}
