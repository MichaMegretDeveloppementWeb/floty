<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Vehicle;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;
use App\Rules\Vehicle\AvailableForPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les 4 cas du tableau ADR-0018 § 5 :
 *
 *   | Cas                                       | Comportement |
 *   |-------------------------------------------|--------------|
 *   | exit_date IS NULL                         | ✅ OK         |
 *   | end < exit_date                           | ✅ OK         |
 *   | start >= exit_date                        | ❌ rejeté    |
 *   | start < exit_date <= end (chevauche)      | ❌ rejeté    |
 *
 * + bordures (end == exit_date - 1, start == exit_date, end == exit_date).
 */
final class AvailableForPeriodTest extends TestCase
{
    use RefreshDatabase;

    private int $activeVehicleId;

    private int $exitedVehicleId;

    private const string EXIT_DATE = '2025-06-15';

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeVehicleId = Vehicle::factory()->create(['exit_date' => null])->id;
        $this->exitedVehicleId = Vehicle::factory()->create([
            'exit_date' => self::EXIT_DATE,
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ])->id;
    }

    #[Test]
    public function vehicule_jamais_sorti_aucun_appel_a_fail(): void
    {
        $rule = new AvailableForPeriod(
            $this->activeVehicleId,
            CarbonImmutable::parse('2025-06-10'),
            CarbonImmutable::parse('2025-06-20'),
        );

        $rule->validate('end_date', '2025-06-20', $this->failClosure($message));
        self::assertNull($message);
    }

    #[Test]
    public function periode_entierement_avant_exit_date_autorisee(): void
    {
        // end = exit_date - 1 → autorisé (limite haute du cas 2).
        $rule = new AvailableForPeriod(
            $this->exitedVehicleId,
            CarbonImmutable::parse('2025-06-01'),
            CarbonImmutable::parse('2025-06-14'),
        );

        $rule->validate('end_date', '2025-06-14', $this->failClosure($message));
        self::assertNull($message);
    }

    #[Test]
    public function periode_entierement_apres_exit_date_rejetee(): void
    {
        // start = exit_date → rejeté (limite basse du cas 3).
        $rule = new AvailableForPeriod(
            $this->exitedVehicleId,
            CarbonImmutable::parse(self::EXIT_DATE),
            CarbonImmutable::parse('2025-07-01'),
        );

        $rule->validate('end_date', '2025-07-01', $this->failClosure($message));
        self::assertNotNull($message);
        self::assertStringContainsString('15/06/2025', (string) $message);
        self::assertStringContainsString('partir de cette date', (string) $message);
    }

    #[Test]
    public function periode_qui_chevauche_exit_date_rejetee(): void
    {
        // start < exit_date <= end → cas 4.
        $rule = new AvailableForPeriod(
            $this->exitedVehicleId,
            CarbonImmutable::parse('2025-06-10'),
            CarbonImmutable::parse('2025-06-20'),
        );

        $rule->validate('end_date', '2025-06-20', $this->failClosure($message));
        self::assertNotNull($message);
        self::assertStringContainsString('15/06/2025', (string) $message);
        self::assertStringContainsString('ne peut pas dépasser', (string) $message);
    }

    #[Test]
    public function periode_qui_finit_pile_sur_exit_date_rejetee(): void
    {
        // end = exit_date → considéré comme chevauchant (cas 4).
        $rule = new AvailableForPeriod(
            $this->exitedVehicleId,
            CarbonImmutable::parse('2025-06-01'),
            CarbonImmutable::parse(self::EXIT_DATE),
        );

        $rule->validate('end_date', self::EXIT_DATE, $this->failClosure($message));
        self::assertNotNull($message);
    }

    #[Test]
    public function vehicule_introuvable_silencieux(): void
    {
        // 999999 = id qui n'existe pas. Une autre rule (`exists:vehicles,id`)
        // couvre ce cas en amont, donc AvailableForPeriod doit rester muet.
        $rule = new AvailableForPeriod(
            999_999,
            CarbonImmutable::parse('2025-06-10'),
            CarbonImmutable::parse('2025-06-20'),
        );

        $rule->validate('end_date', '2025-06-20', $this->failClosure($message));
        self::assertNull($message);
    }

    /**
     * Capture le message du Closure $fail dans la variable passée par
     * référence. Permet d'écrire des assertions claires sans mocker le
     * Closure.
     */
    private function failClosure(?string &$captured): \Closure
    {
        $captured = null;

        return function (string $message) use (&$captured): void {
            $captured = $message;
        };
    }
}
