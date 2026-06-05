<?php

declare(strict_types=1);

namespace Tests\Feature\User\Control;

use App\Data\User\Control\Vehicle\EffectiveControlData;
use App\Models\ControlDefinition;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Services\Control\EffectiveControlResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature de la résolution du cycle de rappel effectif (Chantier B / B3) :
 * override -> definition -> settings (défaut 15 j).
 */
final class EffectiveReminderCycleTest extends TestCase
{
    use RefreshDatabase;

    private function resolveFirst(Vehicle $vehicle): EffectiveControlData
    {
        $controls = app(EffectiveControlResolver::class)->resolve($vehicle, CarbonImmutable::parse('2026-06-05'));

        return $controls[0];
    }

    #[Test]
    public function herite_des_parametres_generaux_quand_rien_n_est_surcharge(): void
    {
        $vehicle = Vehicle::factory()->create();
        ControlDefinition::factory()->create(['reminder_days_before' => null]);

        self::assertSame(15, $this->resolveFirst($vehicle)->effectiveReminderDaysBefore);
    }

    #[Test]
    public function la_definition_surcharge_les_parametres_generaux(): void
    {
        $vehicle = Vehicle::factory()->create();
        ControlDefinition::factory()->create(['reminder_days_before' => 20]);

        self::assertSame(20, $this->resolveFirst($vehicle)->effectiveReminderDaysBefore);
    }

    #[Test]
    public function la_surcharge_vehicule_prime_sur_la_definition(): void
    {
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create(['reminder_days_before' => 20]);
        VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
            'reminder_days_before' => 30,
            'reminder_on_due_day' => false,
            'reminder_repeat_every_days' => 7,
        ]);

        self::assertSame(30, $this->resolveFirst($vehicle)->effectiveReminderDaysBefore);
    }

    #[Test]
    public function le_cycle_herite_reflete_le_controle_global_pas_les_parametres_generaux(): void
    {
        // Le contrôle global surcharge la fréquence (20 j) ; un véhicule sans
        // personnalisation hérite de 20 j, pas des 15 j des paramètres généraux.
        $vehicle = Vehicle::factory()->create();
        ControlDefinition::factory()->create(['reminder_days_before' => 20]);

        $control = $this->resolveFirst($vehicle);

        self::assertSame(20, $control->inheritedReminderDaysBefore);
        self::assertSame(20, $control->effectiveReminderDaysBefore);
    }
}
