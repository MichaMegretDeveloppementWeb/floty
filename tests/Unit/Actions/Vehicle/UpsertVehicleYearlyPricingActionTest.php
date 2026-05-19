<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Vehicle;

use App\Actions\Vehicle\UpsertVehicleYearlyPricingAction;
use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UpsertVehicleYearlyPricingActionTest extends TestCase
{
    use RefreshDatabase;

    private UpsertVehicleYearlyPricingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(UpsertVehicleYearlyPricingAction::class);
    }

    #[Test]
    public function insere_un_nouveau_pricing_quand_aucun_n_existe_pour_l_annee(): void
    {
        $vehicle = Vehicle::factory()->create();

        $result = $this->action->execute(
            $vehicle->id,
            new VehicleYearlyPricingData(
                year: 2024,
                dailyRateCents: 10_000,
                weeklyRateCents: 60_000,
                monthlyRateCents: 200_000,
            ),
        );

        $this->assertSame(2024, $result->year);
        $this->assertSame(10_000, $result->daily_rate_cents);
        $this->assertSame(60_000, $result->weekly_rate_cents);
        $this->assertSame(200_000, $result->monthly_rate_cents);
        $this->assertDatabaseHas('vehicle_yearly_pricings', [
            'vehicle_id' => $vehicle->id,
            'year' => 2024,
            'daily_rate_cents' => 10_000,
        ]);
    }

    #[Test]
    public function met_a_jour_le_pricing_existant_de_facon_idempotente(): void
    {
        $vehicle = Vehicle::factory()->create();
        $existing = VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(8_000, 50_000, 180_000)
            ->create();

        $result = $this->action->execute(
            $vehicle->id,
            new VehicleYearlyPricingData(
                year: 2024,
                dailyRateCents: 12_000,
                weeklyRateCents: 70_000,
                monthlyRateCents: 220_000,
            ),
        );

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(12_000, $result->daily_rate_cents);
        $this->assertSame(70_000, $result->weekly_rate_cents);
        $this->assertSame(220_000, $result->monthly_rate_cents);

        $this->assertSame(
            1,
            VehicleYearlyPricing::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('year', 2024)
                ->count(),
        );
    }

    #[Test]
    public function preserve_les_pricings_d_autres_annees(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2023)
            ->withRates(7_000, 45_000, 160_000)
            ->create();

        $this->action->execute(
            $vehicle->id,
            new VehicleYearlyPricingData(
                year: 2024,
                dailyRateCents: 10_000,
                weeklyRateCents: 60_000,
                monthlyRateCents: 200_000,
            ),
        );

        $this->assertSame(2, VehicleYearlyPricing::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('vehicle_yearly_pricings', [
            'vehicle_id' => $vehicle->id,
            'year' => 2023,
            'daily_rate_cents' => 7_000,
        ]);
    }

    #[Test]
    public function permet_un_tarif_zero_pour_les_vehicules_en_usage_gratuit(): void
    {
        // Tarif 0 documenté (≠ NULL = absence de ligne).
        $vehicle = Vehicle::factory()->create();

        $result = $this->action->execute(
            $vehicle->id,
            new VehicleYearlyPricingData(
                year: 2024,
                dailyRateCents: 0,
                weeklyRateCents: 0,
                monthlyRateCents: 0,
            ),
        );

        $this->assertSame(0, $result->daily_rate_cents);
        $this->assertDatabaseHas('vehicle_yearly_pricings', [
            'vehicle_id' => $vehicle->id,
            'year' => 2024,
            'daily_rate_cents' => 0,
        ]);
    }
}
