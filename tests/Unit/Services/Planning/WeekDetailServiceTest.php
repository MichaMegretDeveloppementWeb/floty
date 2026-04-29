<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Planning;

use App\Data\User\Planning\PreviewTaxesInputData;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Planning\WeekDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WeekDetailServiceTest extends TestCase
{
    use RefreshDatabase;

    private WeekDetailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(WeekDetailService::class);
    }

    #[Test]
    public function build_week_renvoie_7_jours_avec_attributions_eager_loaded(): void
    {
        $year = (int) config('floty.fiscal.available_years')[0];
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat couvrant le lundi de la semaine 8 (1 jour).
        $weekStart = Carbon::now()->setISODate($year, 8)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->toDateString(),
        ]);

        $week = $this->service->buildWeek($vehicle->id, 8, $year);

        self::assertCount(7, $week->days);
        self::assertNotNull($week->days[0]->assignment);
        self::assertNull($week->days[1]->assignment);
        self::assertCount(1, $week->companiesOnWeek);
        self::assertSame(1, $week->companiesOnWeek[0]->days);
    }

    #[Test]
    public function preview_taxes_calcule_le_delta_de_taxe_pour_n_nouvelles_dates(): void
    {
        $year = (int) config('floty.fiscal.available_years')[0];
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat existant 35 jours non-LCD pour produire un before
        // taxable.
        $start = Carbon::create($year, 7, 15);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(34)->toDateString(),
        ]);

        // Nouvelles dates : plage de 35 j non-LCD pour produire un
        // delta taxable (le service de preview crée un contrat
        // synthétique sur [min, max] des dates).
        $newDates = [
            $start->copy()->addDays(60)->toDateString(),
            $start->copy()->addDays(94)->toDateString(),
        ];
        $input = new PreviewTaxesInputData(
            vehicleId: $vehicle->id,
            companyId: $company->id,
            dates: $newDates,
        );

        $preview = $this->service->previewTaxes($input, $year);

        self::assertSame(35, $preview->newDaysCount);
        self::assertSame(35, $preview->existingCumul);
        self::assertSame(70, $preview->futureCumul);
        self::assertNotNull($preview->before);
        self::assertGreaterThan(0.0, $preview->incrementalDue);
    }
}
