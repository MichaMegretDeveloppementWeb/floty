<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contract;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du Service Query - composition DTOs + helper expandToDays
 * (utilisé par le moteur fiscal en 04.F pour le numérateur du prorata,
 * cf. R-2024-002).
 */
final class ContractQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContractQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ContractQueryService::class);
    }

    #[Test]
    public function expand_to_days_inclus_les_deux_bornes(): void
    {
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-05',
            ]);

        $days = $this->service->expandToDays($contract->refresh(), 2024);

        $this->assertSame(
            ['2024-03-01', '2024-03-02', '2024-03-03', '2024-03-04', '2024-03-05'],
            $days,
        );
    }

    #[Test]
    public function expand_to_days_clampe_les_bornes_a_l_annee_demandee(): void
    {
        // Contrat à cheval sur 2023→2024 : seules les dates de 2024
        // doivent ressortir lorsqu'on demande l'année 2024.
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2023-12-29',
                'end_date' => '2024-01-03',
            ]);

        $days = $this->service->expandToDays($contract->refresh(), 2024);

        $this->assertSame(['2024-01-01', '2024-01-02', '2024-01-03'], $days);
    }

    #[Test]
    public function expand_to_days_renvoie_vide_si_le_contrat_est_hors_annee(): void
    {
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2023-05-01',
                'end_date' => '2023-05-10',
            ]);

        $this->assertSame([], $this->service->expandToDays($contract->refresh(), 2024));
    }

    #[Test]
    public function find_contract_data_renvoie_null_si_id_inexistant(): void
    {
        $this->assertNull($this->service->findContractData(999999));
    }

    #[Test]
    public function find_contract_data_compose_le_dto_avec_relations(): void
    {
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15',
            ]);

        $data = $this->service->findContractData($contract->id);

        $this->assertNotNull($data);
        $this->assertSame($contract->id, $data->id);
        $this->assertSame('2024-03-01', $data->startDate);
        $this->assertSame('2024-03-15', $data->endDate);
        // Inclus les deux bornes : 15 jours.
        $this->assertSame(15, $data->durationDays);
    }

    #[Test]
    public function load_contracts_by_pair_for_year_range_charge_le_range_en_1_query_sql(): void
    {
        // F-11-001 · garde-fou perf · le pivot range doit faire **1**
        // SELECT au lieu de N (1 par année). Test isolé du Service ·
        // les autres callers de `loadContractsByPair($year)` (Dashboard,
        // VehiclePeriodConflictsService...) sont scopés à F-21-001/002.
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        // 5 contrats sur 5 années distinctes.
        foreach (range(2020, 2024) as $year) {
            Contract::factory()
                ->forVehicle($vehicle)
                ->forCompany($company)
                ->create([
                    'start_date' => "{$year}-01-01",
                    'end_date' => "{$year}-12-31",
                ]);
        }

        DB::enableQueryLog();
        $byYear = $this->service->loadContractsByPairForYearRange(2020, 2024);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Avant fix · 5 invocations indépendantes de loadContractsByPair
        // = 5 SELECTs FROM contracts. Après fix · 1 SELECT range.
        $contractsQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'from `contracts`'),
        );
        self::assertCount(1, $contractsQueries, 'Expected exactly 1 SELECT FROM contracts query');

        // Vérifie le contenu · 5 années peuplées + chaque pivot a 1 couple.
        self::assertCount(5, $byYear);
        for ($year = 2020; $year <= 2024; $year++) {
            self::assertArrayHasKey($year, $byYear);
            self::assertCount(1, $byYear[$year]->byPair);
        }
    }

    #[Test]
    public function load_contracts_by_pair_for_year_range_dispatche_un_contrat_pluri_annee_dans_chaque_annee_chevauchee(): void
    {
        // Un contrat 2022→2024 doit apparaître dans les 3 pivots.
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany($company)
            ->create([
                'start_date' => '2022-06-01',
                'end_date' => '2024-05-31',
            ]);

        $byYear = $this->service->loadContractsByPairForYearRange(2022, 2024);

        self::assertCount(3, $byYear);
        $key = $vehicle->id.'|'.$company->id;
        for ($year = 2022; $year <= 2024; $year++) {
            self::assertArrayHasKey($key, $byYear[$year]->byPair);
            self::assertCount(1, $byYear[$year]->byPair[$key]);
        }
    }

    #[Test]
    public function load_contracts_by_pair_for_year_range_retourne_des_pivots_vides_pour_les_annees_sans_contrat(): void
    {
        // Aucun contrat en base · les 3 années doivent ressortir avec
        // un ContractsByPair vide (pas omises).
        $byYear = $this->service->loadContractsByPairForYearRange(2020, 2022);

        self::assertCount(3, $byYear);
        for ($year = 2020; $year <= 2022; $year++) {
            self::assertArrayHasKey($year, $byYear);
            self::assertSame([], $byYear[$year]->byPair);
        }
    }
}
