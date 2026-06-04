<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Data\User\Vehicle\VehicleUsageStatsData;
use App\Data\User\Vehicle\VehicleWeekUsageData;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Vehicle\VehicleAggregatesService;
use App\Services\Vehicle\VehicleDetailService;
use App\Services\Vehicle\VehicleListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre `VehicleListingService::listForOptions` + 3 cas timeline de
 * `VehicleAggregatesService::usageStatsForYear` après l'éclatement
 * SRP de `VehicleQueryService` en 3 sous-services thématiques
 * (Lot 4 D09 / F-14-003). Reprend les assertions de l'ancien
 * `VehicleQueryServiceTest` pour garantir l'équivalence sémantique.
 *
 * `findVehicleData` (Detail) et `listPaginated` (Listing) sont testés
 * par les Feature `tests/Feature/User/Vehicle/VehicleControllerTest`
 * (intégration complète repo + agrégats fiscaux + DTO Inertia).
 */
final class VehicleQueryServicesTest extends TestCase
{
    use RefreshDatabase;

    private VehicleListingService $listing;

    private VehicleAggregatesService $aggregates;

    private VehicleDetailService $detail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listing = $this->app->make(VehicleListingService::class);
        $this->aggregates = $this->app->make(VehicleAggregatesService::class);
        $this->detail = $this->app->make(VehicleDetailService::class);
    }

    // ----------------------------------------------------------------
    // VehicleDetailService::historyForVehicle (audit perf 2026-05-16 P0.3)
    // ----------------------------------------------------------------

    #[Test]
    public function history_for_vehicle_couvre_les_annees_passees_du_scope_global(): void
    {
        // Audit perf 2026-05-16 / 02-vehicle.md P0 #1 : l'historique
        // annuel est extrait de findVehicleData() vers une methode
        // dediee historyForVehicle() servie en Inertia::defer. Ce test
        // verifie l'equivalence stricte avec l'ancien comportement ·
        // contrat 2024 → minYear = 2024, currentYear = 2026 →
        // [2025 neutre, 2024 reel] (ordre DESC).
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        $currentYear = (int) Carbon::now()->year;
        $expectedYears = range(2024, $currentYear - 1);

        $history = $this->detail->historyForVehicle($vehicle->id);

        self::assertCount(count($expectedYears), $history);
        // Ordre DESC : index 0 = currentYear - 1
        self::assertSame($currentYear - 1, $history[0]->year);
        // Dernier element : 2024 avec 15 jours utilises
        self::assertSame(2024, $history[count($expectedYears) - 1]->year);
        self::assertSame(15, $history[count($expectedYears) - 1]->daysUsed);
    }

    /**
     * Équivalence stricte (chantier perf Flotte 2026-05-17) ·
     * `costsForVehicleIds` (avec prewarm VFC batch) retourne strictement
     * les MÊMES valeurs `fullYearTax` que `aggregator->vehicleFullYearTax`
     * appelé individuellement. Garantit zéro régression côté calculs.
     * Doctrine `optimisations-conditionnelles.md` stratégie 2.
     */
    #[Test]
    public function costs_for_vehicle_ids_equivalent_au_calcul_individuel_sans_prewarm(): void
    {
        $v1 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v1->id]);
        $v2 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2->id]);
        $v3 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v3->id]);

        $year = 2026;

        // Référence : valeurs sans prewarm batch (1 aggregator FRESH par
        // véhicule pour éviter contamination cache).
        $expected = [];
        foreach ([$v1, $v2, $v3] as $v) {
            $fresh = $this->app->make(FleetFiscalAggregator::class);
            $expected[$v->id] = $fresh->vehicleFullYearTax($v, $year);
        }

        // Service à tester : avec prewarm batch.
        $actual = $this->listing->costsForVehicleIds([$v1->id, $v2->id, $v3->id], $year);

        foreach ([$v1, $v2, $v3] as $v) {
            self::assertSame(
                $expected[$v->id],
                $actual[$v->id]['fullYearTax'],
                "fullYearTax doit être strictement identique pour véhicule {$v->id}",
            );
        }
    }

    /**
     * Garde-fou perf : `costsForVehicleIds` exécute exactement 2 queries
     * VFC batchées indépendamment du nombre de véhicules (pas N+1) ·
     *   1. `findByIdsIndexed` eager-load `fiscalCharacteristics` (WHERE
     *      effective_to IS NULL : 1 query batched)
     *   2. `prewarmFullYearForVehicles` (segments effectifs pour l'année ·
     *      1 query batched via `findEffectiveSegmentsForYearBatch`)
     *
     * Avant ce chantier : `vehicleFullYearTax` appelé en boucle déclenchait
     * N queries VFC individuelles (N+1 monstrueux).
     */
    #[Test]
    public function costs_for_vehicle_ids_supprime_le_n_plus_1_vfc_via_prewarm_batch(): void
    {
        $vehicleIds = [];
        for ($i = 0; $i < 5; $i++) {
            $v = Vehicle::factory()->create();
            VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v->id]);
            $vehicleIds[] = $v->id;
        }

        DB::enableQueryLog();
        $this->listing->costsForVehicleIds($vehicleIds, 2026);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $vfcQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'from `vehicle_fiscal_characteristics`'),
        );

        self::assertCount(
            2,
            $vfcQueries,
            'Expected exactly 2 batched VFC queries (eager-load + prewarm), got '.count($vfcQueries),
        );
    }

    #[Test]
    public function list_for_light_selector_inclut_les_vehicules_sortis_avec_is_exited_marque(): void
    {
        // Cf. ADR-0018 § 4 + chantier E.5 : le picker véhicule des
        // formulaires Contrats inclut les véhicules retirés pour
        // permettre la consultation et l'édition rétroactive ; le
        // frontend distingue actifs/retirés via `isExited`.
        //
        // S2.4 + S2.5 (audit perf 2026-05-16) : renommé `listForOptions`
        // → `listForLightSelector` après suppression de la version
        // lourde avec `fullYearTaxByYear` pré-calculé (192 pipeline
        // runs gaspillés). Calcul taxe pleine désormais on-demand
        // via endpoint AJAX `vehicles.full-year-tax`.
        Vehicle::factory()->create(['exit_date' => null]);
        Vehicle::factory()->create([
            'exit_date' => '2024-01-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);

        $items = $this->listing->listForLightSelector()->toArray();

        self::assertCount(2, $items);

        $exited = array_values(array_filter($items, fn (array $i): bool => $i['isExited'] === true));
        $active = array_values(array_filter($items, fn (array $i): bool => $i['isExited'] === false));

        self::assertCount(1, $exited);
        self::assertCount(1, $active);
        self::assertSame('2024-01-15', $exited[0]['exitDate']);
        self::assertNull($active[0]['exitDate']);
    }

    // ----------------------------------------------------------------
    // Timeline 52 semaines : split indispos réductrices vs non-réductrices
    // (chantier #1, ADR-0016 + ADR-0019).
    // ----------------------------------------------------------------

    #[Test]
    public function timeline_indispo_reductrice_chevauchant_un_contrat_7j_reste_visible(): void
    {
        // Pré-ADR-0019 le clamp `min(..., 7 - totalDays)` masquait toute
        // indispo chevauchant un contrat couvrant la semaine entière.
        // Ce test vérifie que la donnée brute remonte désormais.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        // Lundi 5 → dimanche 11 mai 2026 = ISO week 19, 7 jours de contrat.
        Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany($company)
            ->create([
                'start_date' => '2026-05-04',
                'end_date' => '2026-05-10',
            ]);

        // Indispo PoundPublic 3 jours sur la même semaine.
        VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-06',
            'end_date' => '2026-05-08',
        ]);

        $stats = $this->aggregates->usageStatsForYear($vehicle->id, 2026);
        $week19 = $this->weekRow($stats, 19);

        self::assertSame(7, $week19->totalDays);
        self::assertSame(3, $week19->reductiveVehicleEventDays);
        self::assertSame(0, $week19->nonReductiveUnavailabilityDays);
    }

    #[Test]
    public function timeline_split_reductrice_et_non_reductrice_sur_la_meme_semaine(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        // ISO week 19 = lundi 4 mai → dimanche 10 mai 2026.
        // 2 jours réducteurs (PoundPublic) + 4 jours non-réducteurs (Maintenance),
        // sans overlap entre eux pour éviter la priorité.
        VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
        ]);
        VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-06',
            'end_date' => '2026-05-09',
        ]);

        $stats = $this->aggregates->usageStatsForYear($vehicle->id, 2026);
        $week19 = $this->weekRow($stats, 19);

        self::assertSame(2, $week19->reductiveVehicleEventDays);
        self::assertSame(4, $week19->nonReductiveUnavailabilityDays);
    }

    #[Test]
    public function timeline_meme_date_couverte_par_2_types_priorite_reductrice(): void
    {
        // ADR-0019 autorise des indispos de types différents sur la
        // même date. Pour la timeline, la date doit compter une seule
        // fois et le type réducteur prime (impact fiscal = info la plus
        // parlante côté UI).
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        // Maintenance les 4-5 mai (ISO week 19).
        VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
        ]);
        // PoundPublic chevauche le 5 mai et ajoute le 6 mai.
        VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-06',
        ]);

        $stats = $this->aggregates->usageStatsForYear($vehicle->id, 2026);
        $week19 = $this->weekRow($stats, 19);

        // 4 mai = nonReductive (1j), 5 mai = reductive (priorité), 6 mai = reductive
        // → 2 réductrices + 1 non-réductrice (pas de double comptage du 5 mai).
        self::assertSame(2, $week19->reductiveVehicleEventDays);
        self::assertSame(1, $week19->nonReductiveUnavailabilityDays);
    }

    private function weekRow(
        VehicleUsageStatsData $stats,
        int $weekNumber,
    ): VehicleWeekUsageData {
        foreach ($stats->weeklyBreakdown as $row) {
            if ($row->weekNumber === $weekNumber) {
                return $row;
            }
        }

        self::fail("Week {$weekNumber} not found in weeklyBreakdown");
    }
}
