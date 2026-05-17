<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contract;

use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Contract\ContractQueryService;
use Carbon\Carbon;
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

    #[Test]
    public function costs_for_contract_ids_equivalent_a_find_contract_tax_breakdown_lld_mono_vfc(): void
    {
        // Garantie · le totalTax servi à l'Index Contracts doit être
        // strictement équivalent au totalDue calculé par le pipeline
        // segmenté affiché sur la page Show. Pas d'approximation
        // linéaire jours/365 qui ignorerait les exonérations ou les
        // scissions VFC.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $contract = Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany($company)
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-05-31',
            ]);

        $costs = $this->service->costsForContractIds([$contract->id]);
        $breakdown = $this->service->findContractTaxBreakdown($contract->id);

        self::assertNotNull($breakdown);
        self::assertGreaterThan(0.0, $breakdown->totalDue);
        self::assertSame($breakdown->totalDue, $costs[$contract->id]['totalTax']);
    }

    #[Test]
    public function costs_for_contract_ids_retourne_zero_pour_un_contrat_lcd_exonere(): void
    {
        // R-2024-021 · location LCD ≤ 30 j entièrement exonérée
        // (daysAssigned = 0 → totalDue = 0). Test critique · l'Index
        // doit afficher 0 € pour les LCD, pas l'approximation
        // `fullYearTax × 15/365` non-nulle qui était servie avant le fix.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $contract = Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany($company)
            ->create([
                'start_date' => '2024-04-01',
                'end_date' => '2024-04-15', // 15 j → LCD exonéré
            ]);

        $costs = $this->service->costsForContractIds([$contract->id]);
        $breakdown = $this->service->findContractTaxBreakdown($contract->id);

        self::assertNotNull($breakdown);
        self::assertSame(0.0, $breakdown->totalDue);
        self::assertSame(0.0, $costs[$contract->id]['totalTax']);
    }

    #[Test]
    public function find_contract_tax_breakdown_peuple_les_champs_hypothetiques_pour_un_lcd(): void
    {
        // Show page · pour un contrat LCD exonéré (totalDue=0), le
        // breakdown doit exposer `hypotheticalTotalDueIfNoLcd` (ce que
        // le contrat coûterait s'il était requalifié en LLD). Vrai
        // calcul pipeline via opt-out R-2024-021 (mécanisme cluster
        // requalification), pas l'approximation linéaire.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $contract = Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany($company)
            ->create([
                'start_date' => '2024-04-01',
                'end_date' => '2024-04-25', // 25 j → LCD exonéré R-2024-021
                'contract_type' => ContractType::Lcd,
            ]);

        $breakdown = $this->service->findContractTaxBreakdown($contract->id);

        self::assertNotNull($breakdown);
        self::assertSame(0.0, $breakdown->totalDue);

        $year = $breakdown->years[0];
        self::assertSame(0.0, $year->totalDue);
        self::assertNotNull($year->hypotheticalTotalDueIfNoLcd, 'hypothétique doit être peuplé pour un LCD exonéré');
        self::assertGreaterThan(0.0, $year->hypotheticalTotalDueIfNoLcd);
        self::assertNotNull($year->hypotheticalCo2DueIfNoLcd);
        self::assertNotNull($year->hypotheticalPollutantsDueIfNoLcd);
        // Sanity · CO₂ + polluants ≈ total (tolérance arrondi half-up).
        self::assertEqualsWithDelta(
            $year->hypotheticalCo2DueIfNoLcd + $year->hypotheticalPollutantsDueIfNoLcd,
            $year->hypotheticalTotalDueIfNoLcd,
            0.01,
        );
    }

    #[Test]
    public function find_contract_tax_breakdown_laisse_les_hypothetiques_null_pour_un_lld(): void
    {
        // Sécurité · un LLD n'est jamais exonéré R-2024-021, donc
        // pas d'hypothétique pertinent. Le code skip le calcul opt-out
        // (early-return) pour éviter de payer le pipeline 2 fois.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $contract = Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany($company)
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-05-31', // 92 j → LLD
                'contract_type' => ContractType::Lld,
            ]);

        $breakdown = $this->service->findContractTaxBreakdown($contract->id);

        self::assertNotNull($breakdown);
        $year = $breakdown->years[0];
        self::assertGreaterThan(0.0, $year->totalDue);
        self::assertNull($year->hypotheticalTotalDueIfNoLcd);
        self::assertNull($year->hypotheticalCo2DueIfNoLcd);
        self::assertNull($year->hypotheticalPollutantsDueIfNoLcd);
    }

    #[Test]
    public function costs_for_contract_ids_equivalent_au_breakdown_total_pour_contrat_multi_vfc(): void
    {
        // Contrat à cheval sur 2 VFC (changement de caractéristiques
        // CO₂ en cours de contrat) · l'approximation linéaire serait
        // fausse car la moitié du contrat utilise un tarif plus élevé.
        // Le fix doit garantir équivalence stricte avec le breakdown
        // segmenté.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::create(2024, 1, 1),
            'effective_to' => Carbon::create(2024, 6, 30),
            'co2_wltp' => 100,
        ]);
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::create(2024, 7, 1),
            'effective_to' => null,
            'co2_wltp' => 175,
        ]);
        $company = Company::factory()->create();

        $contract = Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany($company)
            ->create([
                'start_date' => '2024-06-01',
                'end_date' => '2024-07-31',
            ]);

        $costs = $this->service->costsForContractIds([$contract->id]);
        $breakdown = $this->service->findContractTaxBreakdown($contract->id);

        self::assertNotNull($breakdown);
        self::assertGreaterThan(0.0, $breakdown->totalDue);
        // L'année doit avoir 2 segments (la scission VFC 30/06 → 01/07
        // coupe le contrat 01/06 → 31/07 en 2).
        self::assertCount(1, $breakdown->years);
        self::assertCount(2, $breakdown->years[0]->segments);
        // Équivalence stricte · le total servi à l'Index = total breakdown.
        self::assertSame($breakdown->totalDue, $costs[$contract->id]['totalTax']);
    }
}
