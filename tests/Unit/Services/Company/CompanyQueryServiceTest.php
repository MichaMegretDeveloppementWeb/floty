<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Company;

use App\Models\Assignment;
use App\Models\Company;
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
        $year = (int) config('floty.fiscal.current_year');
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        $start = Carbon::create($year, 5, 1);
        for ($i = 0; $i < 35; $i++) {
            Assignment::factory()->create([
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'date' => $start->copy()->addDays($i)->toDateString(),
            ]);
        }

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
