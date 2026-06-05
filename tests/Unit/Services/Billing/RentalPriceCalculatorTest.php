<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use App\Services\Billing\RentalPriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le batch loyer multi-entreprises (lot 3 optimisation requêtes).
 *
 * `forCompaniesAndYear` collapse le N+1 de la colonne loyer de l'index
 * entreprises (un `forCompanyAndYear` par entreprise). Il DOIT produire
 * pour chaque entreprise un montant strictement identique à l'appel
 * individuel, sinon deux chemins divergents = montants faux silencieux.
 */
final class RentalPriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private RentalPriceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = $this->app->make(RentalPriceCalculator::class);
    }

    #[Test]
    public function for_companies_and_year_equivaut_aux_appels_individuels(): void
    {
        $year = 2026;

        // A · 1 véhicule tarifé, contrat partiel sur l'année.
        $a = Company::factory()->create();
        $va = Vehicle::factory()->create();
        $this->price($va, $year, 5000, 25000, 80000);
        Contract::factory()->forVehicle($va)->forCompany($a)->create([
            'start_date' => '2026-01-10',
            'end_date' => '2026-03-20',
        ]);

        // B · 1 véhicule tarifé (année pleine) + 1 véhicule SANS tarif
        // (le total doit retomber sur null pour toute l'entreprise).
        $b = Company::factory()->create();
        $vb1 = Vehicle::factory()->create();
        $this->price($vb1, $year, 4000, 20000, 60000);
        $vb2 = Vehicle::factory()->create();
        Contract::factory()->forVehicle($vb1)->forCompany($b)->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        Contract::factory()->forVehicle($vb2)->forCompany($b)->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]);

        // C · aucun contrat sur l'année → 0.
        $c = Company::factory()->create();

        $companyIds = [$a->id, $b->id, $c->id];
        $batch = $this->calc->forCompaniesAndYear($companyIds, $year);

        foreach ($companyIds as $id) {
            self::assertSame(
                $this->calc->forCompanyAndYear($id, $year),
                $batch[$id],
                "entreprise {$id} · batch === individuel",
            );
        }

        // Garde-fous sémantiques.
        self::assertNotNull($batch[$a->id], 'A · tarifs présents → montant');
        self::assertNull($batch[$b->id], 'B · un véhicule sans tarif → null');
        self::assertSame(0, $batch[$c->id], 'C · aucun contrat → 0');
    }

    #[Test]
    public function for_companies_and_year_collapse_les_queries(): void
    {
        $year = 2026;
        foreach (range(1, 3) as $i) {
            $company = Company::factory()->create();
            $vehicle = Vehicle::factory()->create();
            $this->price($vehicle, $year, 4000, 20000, 60000);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2026-02-01',
                'end_date' => '2026-04-30',
            ]);
        }

        $companyIds = Company::query()->pluck('id')->all();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->calc->forCompaniesAndYear($companyIds, $year);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 3 requêtes constantes : contrats + véhicules (eager) + tarifs,
        // quel que soit le nombre d'entreprises (vs 3 x N en N+1 avant).
        self::assertSame(
            3,
            count($queries),
            'batch loyer = 3 requêtes constantes (contrats + véhicules + tarifs), pas de N+1',
        );
    }

    #[Test]
    public function for_company_and_years_equivaut_aux_appels_annuels(): void
    {
        $company = Company::factory()->create();

        // V1 · tarifé 2024-2026, contrats séquentiels sur 2024 puis 2025.
        $v1 = Vehicle::factory()->create();
        foreach ([2024, 2025, 2026] as $y) {
            $this->price($v1, $y, 4000, 20000, 60000);
        }
        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => '2024-06-01',
            'end_date' => '2024-08-31',
        ]);
        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => '2025-03-01',
            'end_date' => '2025-04-15',
        ]);

        // V2 · tarifé 2026, contrat 2026.
        $v2 = Vehicle::factory()->create();
        $this->price($v2, 2026, 5000, 25000, 80000);
        Contract::factory()->forVehicle($v2)->forCompany($company)->create([
            'start_date' => '2026-02-01',
            'end_date' => '2026-05-31',
        ]);

        // V3 · contrat 2025 SANS tarif → 2025 doit retomber sur null.
        $v3 = Vehicle::factory()->create();
        Contract::factory()->forVehicle($v3)->forCompany($company)->create([
            'start_date' => '2025-09-01',
            'end_date' => '2025-09-30',
        ]);

        $years = [2024, 2025, 2026];
        $batch = $this->calc->forCompanyAndYears($company->id, $years);

        foreach ($years as $year) {
            self::assertSame(
                $this->calc->forCompanyAndYear($company->id, $year),
                $batch[$year],
                "année {$year} · cross-années === annuel",
            );
        }

        self::assertNull($batch[2025], '2025 · V3 sans tarif → null');
        self::assertNotNull($batch[2024], '2024 · tarifé');
    }

    #[Test]
    public function for_company_and_years_collapse_les_queries(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        foreach ([2024, 2025, 2026] as $y) {
            $this->price($vehicle, $y, 4000, 20000, 60000);
        }
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-02-01',
            'end_date' => '2024-04-30',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2026-02-01',
            'end_date' => '2026-04-30',
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->calc->forCompanyAndYears($company->id, [2024, 2025, 2026]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 3 requêtes constantes : contrats + véhicules (eager) + tarifs,
        // quel que soit le nombre d'années (vs 3 x N en N+1 annuel).
        self::assertSame(
            3,
            count($queries),
            'loyer cross-années = 3 requêtes constantes, pas de N+1 par année',
        );
    }

    private function price(Vehicle $vehicle, int $year, int $daily, int $weekly, int $monthly): void
    {
        VehicleYearlyPricing::factory()->create([
            'vehicle_id' => $vehicle->id,
            'year' => $year,
            'daily_rate_cents' => $daily,
            'weekly_rate_cents' => $weekly,
            'monthly_rate_cents' => $monthly,
        ]);
    }
}
