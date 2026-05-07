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
    public function build_week_renvoie_7_jours_avec_contrats_eager_loaded(): void
    {
        $year = 2024;
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
        self::assertNotNull($week->days[0]->contract);
        self::assertNull($week->days[1]->contract);
        self::assertCount(1, $week->companiesOnWeek);
        self::assertSame(1, $week->companiesOnWeek[0]->days);
    }

    #[Test]
    public function preview_taxes_renvoie_le_cout_standalone_du_contrat(): void
    {
        // Sémantique : LCD/LLD se calcule par contrat individuellement
        // (pas de cumul annuel). Le preview ignore tout autre contrat
        // existant pour le même couple véhicule × entreprise.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        // Plage 35 j (>30 → LLD) — coût taxable.
        $start = Carbon::create($year, 7, 15);
        $dates = [
            $start->toDateString(),
            $start->copy()->addDays(34)->toDateString(),
        ];

        $preview = $this->service->previewTaxes(
            new PreviewTaxesInputData(
                vehicleId: $vehicle->id,
                companyId: $company->id,
                dates: $dates,
            ),
            $year,
        );

        self::assertSame($year, $preview->fiscalYear);
        self::assertSame(35, $preview->daysCount);
        self::assertGreaterThan(0.0, $preview->breakdown->totalDue);
    }

    #[Test]
    public function preview_taxes_ignore_les_autres_contrats_du_couple(): void
    {
        // Régression : avant le passage au calcul standalone, le service
        // calculait un delta vs cumul existant — un contrat préexistant
        // pour le même couple déformait le résultat. Désormais le
        // preview est strictement standalone.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        // Contrat préexistant 35 j sur ce couple.
        $start = Carbon::create($year, 7, 15);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(34)->toDateString(),
        ]);

        // Preview pour les MÊMES dates — sans cumul, le résultat doit
        // refléter le coût standalone du contrat (35 j, taxe non nulle).
        $sameDates = [
            $start->toDateString(),
            $start->copy()->addDays(34)->toDateString(),
        ];

        $preview = $this->service->previewTaxes(
            new PreviewTaxesInputData(
                vehicleId: $vehicle->id,
                companyId: $company->id,
                dates: $sameDates,
            ),
            $year,
        );

        self::assertSame(35, $preview->daysCount);
        self::assertGreaterThan(0.0, $preview->breakdown->totalDue);
    }
}
