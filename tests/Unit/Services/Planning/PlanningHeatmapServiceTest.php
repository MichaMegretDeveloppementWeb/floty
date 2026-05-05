<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Planning;

use App\Models\Company;
use App\Models\Contract;
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
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat 1 jour en semaine 10 (durée=1 → LCD ≤ 30 j, mais ce
        // test vérifie la heatmap brute (densité de jours occupés),
        // pas la fiscalité - donc l'exonération LCD n'invalide pas
        // l'assertion `weeks[9] = 1`).
        $weekStart = Carbon::now()->setISODate($year, 10)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->toDateString(),
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
