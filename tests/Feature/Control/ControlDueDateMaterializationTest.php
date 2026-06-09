<?php

declare(strict_types=1);

namespace Tests\Feature\Control;

use App\Enums\Control\ControlScheduleStatus;
use App\Models\ControlDefinition;
use App\Models\ControlExecution;
use App\Models\ControlReminderSettings;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Services\Control\ControlDueDateRecomputeService;
use App\Services\Control\FleetControlScheduleScanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Matérialisation du cache `vehicles.controls_due_from` (Option 3 du point 4).
 * Référence figée à 2026-06-09, fenêtre de rappel par défaut 30 jours.
 */
final class ControlDueDateMaterializationTest extends TestCase
{
    use RefreshDatabase;

    private const string TODAY = '2026-06-09';

    private const int DAYS_BEFORE = 30;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::TODAY));
        ControlReminderSettings::singleton()->update(['days_before' => self::DAYS_BEFORE]);
    }

    private function recompute(): ControlDueDateRecomputeService
    {
        return app(ControlDueDateRecomputeService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicle(string $anchorDate, array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'first_french_registration_date' => $anchorDate,
            'first_origin_registration_date' => $anchorDate,
            'first_economic_use_date' => $anchorDate,
            'acquisition_date' => $anchorDate,
        ], $attributes));
    }

    private function technicalControl(): ControlDefinition
    {
        return ControlDefinition::factory()->create([
            'name' => 'Contrôle technique',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ]);
    }

    private function dueFromOf(Vehicle $vehicle): ?string
    {
        return $vehicle->fresh()->controls_due_from?->toDateString();
    }

    #[Test]
    public function never_executed_due_from_est_l_echeance_initiale_moins_la_fenetre(): void
    {
        $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');

        $this->recompute()->forVehicleId($vehicle->id);

        // Échéance 2018 + 4 ans = 2022-01-01 ; due_from = échéance - 30 j.
        $expected = CarbonImmutable::parse('2022-01-01')->subDays(self::DAYS_BEFORE)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function aucun_controle_actif_donne_null(): void
    {
        // Aucune définition de contrôle en base.
        $vehicle = $this->vehicle('2018-01-01');

        $this->recompute()->forVehicleId($vehicle->id);

        self::assertNull($this->dueFromOf($vehicle));
    }

    #[Test]
    public function due_from_recule_avec_la_derniere_execution(): void
    {
        $definition = $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');
        ControlExecution::factory()->create([
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'executed_on' => '2024-03-10',
        ]);

        $this->recompute()->forVehicleId($vehicle->id);

        // Dernière exécution 2024-03-10 + cycle 2 ans = 2026-03-10 ; - 30 j.
        $expected = CarbonImmutable::parse('2026-03-10')->subDays(self::DAYS_BEFORE)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function fenetre_de_rappel_par_controle_est_respectee(): void
    {
        $definition = $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');
        VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
            'reminder_days_before' => 90,
        ]);

        $this->recompute()->forVehicleId($vehicle->id);

        // Échéance 2022-01-01 ; fenêtre override 90 j (et non 30).
        $expected = CarbonImmutable::parse('2022-01-01')->subDays(90)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function controle_en_pause_est_exclu(): void
    {
        $definition = $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');
        VehicleControlOverride::factory()->overrideOf($definition)->paused()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $this->recompute()->forVehicleId($vehicle->id);

        self::assertNull($this->dueFromOf($vehicle));
    }

    #[Test]
    public function controle_tombant_apres_une_sortie_planifiee_est_exclu(): void
    {
        // Immatriculé 2024 -> échéance 2028, après la sortie planifiée 2026-12-31.
        $this->technicalControl();
        $vehicle = $this->vehicle('2024-01-01', ['exit_date' => '2026-12-31', 'exit_reason' => 'sold']);

        $this->recompute()->forVehicleId($vehicle->id);

        self::assertNull($this->dueFromOf($vehicle));
    }

    #[Test]
    public function due_from_est_le_minimum_sur_plusieurs_controles(): void
    {
        // Deux contrôles : échéances 2020 (2 ans) et 2022 (4 ans). MIN attendu = 2020.
        ControlDefinition::factory()->create([
            'name' => 'Contrôle A',
            'initial_duration_value' => 2,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ]);
        ControlDefinition::factory()->create([
            'name' => 'Contrôle B',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ]);
        $vehicle = $this->vehicle('2018-01-01');

        $this->recompute()->forVehicleId($vehicle->id);

        $expected = CarbonImmutable::parse('2020-01-01')->subDays(self::DAYS_BEFORE)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function invariant_colonne_equivaut_au_scanner_sur_la_flotte(): void
    {
        // Jeu varié : dû, à venir, sans contrôle, sortie future.
        $this->technicalControl();
        $this->vehicle('2018-01-01');                  // en retard
        $this->vehicle('2025-06-01');                  // à venir (échéance 2029)
        $this->vehicle('2024-01-01', ['exit_date' => '2026-12-31', 'exit_reason' => 'sold']); // contrôle post-sortie
        Vehicle::factory()->create([                   // sans aucune date partagée mais avec contrôle
            'first_french_registration_date' => '2019-09-09',
            'first_origin_registration_date' => '2019-09-09',
            'first_economic_use_date' => '2019-09-09',
            'acquisition_date' => '2019-09-09',
        ]);

        $this->recompute()->forFleet();

        $today = CarbonImmutable::parse(self::TODAY);
        $scanner = app(FleetControlScheduleScanner::class);

        foreach (Vehicle::all() as $vehicle) {
            $results = $scanner->scanForVehicles([$vehicle], $today)[$vehicle->id] ?? [];
            $scannerSaysDue = collect($results)->contains(
                fn ($r): bool => in_array($r->scheduleStatus, [
                    ControlScheduleStatus::Overdue,
                    ControlScheduleStatus::DueToday,
                    ControlScheduleStatus::DueSoon,
                ], true),
            );

            $dueFrom = $vehicle->fresh()->controls_due_from;
            $exitDate = $vehicle->exit_date?->toImmutable();
            $columnSaysDue = $dueFrom !== null
                && $dueFrom->toImmutable()->lessThanOrEqualTo($today)
                && ($exitDate === null || $exitDate->greaterThan($today));

            self::assertSame(
                $scannerSaysDue,
                $columnSaysDue,
                "Divergence colonne/scanner pour le véhicule {$vehicle->license_plate}",
            );
        }
    }

    #[Test]
    public function la_commande_de_recalcul_remplit_la_colonne(): void
    {
        $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');

        $this->artisan('controls:recompute-due-dates')->assertSuccessful();

        self::assertNotNull($this->dueFromOf($vehicle));
    }
}
