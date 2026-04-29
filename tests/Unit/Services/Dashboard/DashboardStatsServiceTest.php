<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalRule;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Dashboard\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie l'orchestration des 4 repos + agrégateur fiscal côté
 * `DashboardStatsService` post-migration.
 */
final class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DashboardStatsService::class);
    }

    #[Test]
    public function compute_stats_renvoie_les_compteurs_et_la_taxe_agregee(): void
    {
        $year = (int) config('floty.fiscal.available_years')[0];
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        FiscalRule::factory()->create(['fiscal_year' => $year, 'is_active' => true]);
        // Contrat 35 jours non-LCD pour produire un cumul fiscal taxable.
        $start = Carbon::create($year, 6, 15);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(34)->toDateString(),
        ]);

        $stats = $this->service->computeStats($year)->toArray();

        self::assertSame(1, $stats['vehiclesCount']);
        self::assertSame(1, $stats['companiesCount']);
        self::assertSame(35, $stats['assignmentsYear']);
        self::assertSame(1, $stats['fiscalRulesCount']);
        self::assertGreaterThan(0.0, $stats['totalTaxDue']);
    }
}
