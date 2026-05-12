<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Data\User\Vehicle\VehicleUsageStatsData;
use App\Data\User\Vehicle\VehicleWeekUsageData;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\VehicleQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie l'orchestration repo + agrégateur fiscal + mapping DTO du
 * service `VehicleQueryService` post-migration vers les Repositories.
 */
final class VehicleQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(VehicleQueryService::class);
    }

    #[Test]
    public function list_for_options_inclut_les_vehicules_sortis_avec_is_exited_marque(): void
    {
        // Cf. ADR-0018 § 4 + chantier E.5 : le picker véhicule des
        // formulaires Contrats inclut les véhicules retirés pour
        // permettre la consultation et l'édition rétroactive ; le
        // frontend distingue actifs/retirés via `isExited`.
        Vehicle::factory()->create(['exit_date' => null]);
        Vehicle::factory()->create([
            'exit_date' => '2024-01-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);

        $items = $this->service->listForOptions()->toArray();

        self::assertCount(2, $items);

        $exited = array_values(array_filter($items, fn (array $i): bool => $i['isExited'] === true));
        $active = array_values(array_filter($items, fn (array $i): bool => $i['isExited'] === false));

        self::assertCount(1, $exited);
        self::assertCount(1, $active);
        self::assertSame('2024-01-15', $exited[0]['exitDate']);
        self::assertNull($active[0]['exitDate']);
    }

    // ----------------------------------------------------------------
    // Timeline 52 semaines · split indispos réductrices vs non-réductrices
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
        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-06',
            'end_date' => '2026-05-08',
        ]);

        $stats = $this->service->usageStatsForYear($vehicle->id, 2026);
        $week19 = $this->weekRow($stats, 19);

        self::assertSame(7, $week19->totalDays);
        self::assertSame(3, $week19->reductiveUnavailabilityDays);
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
        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
        ]);
        Unavailability::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-06',
            'end_date' => '2026-05-09',
        ]);

        $stats = $this->service->usageStatsForYear($vehicle->id, 2026);
        $week19 = $this->weekRow($stats, 19);

        self::assertSame(2, $week19->reductiveUnavailabilityDays);
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
        Unavailability::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
        ]);
        // PoundPublic chevauche le 5 mai et ajoute le 6 mai.
        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-06',
        ]);

        $stats = $this->service->usageStatsForYear($vehicle->id, 2026);
        $week19 = $this->weekRow($stats, 19);

        // 4 mai = nonReductive (1j), 5 mai = reductive (priorité), 6 mai = reductive
        // → 2 réductrices + 1 non-réductrice (pas de double comptage du 5 mai).
        self::assertSame(2, $week19->reductiveUnavailabilityDays);
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
