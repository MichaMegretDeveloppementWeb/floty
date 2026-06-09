<?php

declare(strict_types=1);

namespace Tests\Feature\Control;

use App\Models\ControlDefinition;
use App\Models\ControlExecution;
use App\Models\ControlReminderSettings;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Services\Control\ControlDueDateRecomputeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Liveness du cache `vehicles.controls_due_from` via observers (chaque écriture
 * d'une source recalcule) + garde-fou de détection de dérive. Référence figée
 * à 2026-06-09, fenêtre de rappel par défaut 30 jours.
 */
final class ControlDueDateObserverTest extends TestCase
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
    public function creer_un_vehicule_remplit_la_colonne(): void
    {
        $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');

        $expected = CarbonImmutable::parse('2022-01-01')->subDays(self::DAYS_BEFORE)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function creer_une_definition_recalcule_la_flotte(): void
    {
        // Véhicule sans aucune définition -> colonne null...
        $vehicle = $this->vehicle('2018-01-01');
        self::assertNull($this->dueFromOf($vehicle));

        // ...puis l'ajout d'une définition globale recalcule toute la flotte.
        $this->technicalControl();
        self::assertNotNull($this->dueFromOf($vehicle));
    }

    #[Test]
    public function enregistrer_une_execution_decale_la_colonne(): void
    {
        $definition = $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');

        ControlExecution::factory()->create([
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'executed_on' => '2024-03-10',
        ]);

        $expected = CarbonImmutable::parse('2026-03-10')->subDays(self::DAYS_BEFORE)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function mettre_en_pause_un_controle_vide_la_colonne(): void
    {
        $definition = $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');
        self::assertNotNull($this->dueFromOf($vehicle));

        VehicleControlOverride::factory()->overrideOf($definition)->paused()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        self::assertNull($this->dueFromOf($vehicle));
    }

    #[Test]
    public function changer_une_ancre_recalcule_le_vehicule(): void
    {
        $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');

        $vehicle->update([
            'first_french_registration_date' => '2020-01-01',
            'first_origin_registration_date' => '2020-01-01',
            'first_economic_use_date' => '2020-01-01',
            'acquisition_date' => '2020-01-01',
        ]);

        $expected = CarbonImmutable::parse('2024-01-01')->subDays(self::DAYS_BEFORE)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function changer_days_before_recalcule_la_flotte(): void
    {
        $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');

        ControlReminderSettings::singleton()->update(['days_before' => 90]);

        $expected = CarbonImmutable::parse('2022-01-01')->subDays(90)->toDateString();
        self::assertSame($expected, $this->dueFromOf($vehicle));
    }

    #[Test]
    public function la_derive_est_detectee_et_signalee(): void
    {
        $this->technicalControl();
        $vehicle = $this->vehicle('2018-01-01');
        self::assertNotNull($this->dueFromOf($vehicle));

        // Corruption directe (query-builder) qui contourne les observers.
        DB::table('vehicles')->where('id', $vehicle->id)->update(['controls_due_from' => null]);

        $drift = app(ControlDueDateRecomputeService::class)->detectDrift();
        self::assertArrayHasKey($vehicle->id, $drift);

        $this->artisan('controls:verify-due-dates')
            ->expectsOutputToContain('Dérive détectée')
            ->assertSuccessful();
    }
}
