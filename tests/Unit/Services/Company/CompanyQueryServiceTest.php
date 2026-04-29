<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Company;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Company\CompanyQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie l'orchestration repos + agrégateur fiscal du service
 * `CompanyQueryService` post-migration vers les Repositories.
 */
final class CompanyQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompanyQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CompanyQueryService::class);
    }

    #[Test]
    public function list_for_fleet_view_aggrege_jours_et_taxe_par_entreprise(): void
    {
        $year = (int) config('floty.fiscal.available_years')[0];
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat 35 jours non-LCD (start 15 du mois → ne tombe pas sur
        // un mois civil entier, durée > 30 → bien taxé en R-2024-021).
        $start = Carbon::create($year, 5, 15);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(34)->toDateString(),
        ]);

        $result = $this->service->listForFleetView($year)->toArray();

        self::assertCount(1, $result);
        self::assertSame($company->id, $result[0]['id']);
        self::assertSame(35, $result[0]['daysUsed']);
        self::assertGreaterThan(0.0, $result[0]['annualTaxDue']);
    }

    #[Test]
    public function list_for_options_filtre_les_inactives(): void
    {
        Company::factory()->create(['is_active' => true]);
        Company::factory()->create(['is_active' => false]);

        $result = $this->service->listForOptions();

        self::assertCount(1, $result->toArray());
    }

    #[Test]
    public function color_options_renvoie_un_dto_par_couleur(): void
    {
        $result = $this->service->colorOptions()->toArray();

        self::assertNotEmpty($result);
        self::assertArrayHasKey('value', $result[0]);
        self::assertArrayHasKey('label', $result[0]);
    }
}
