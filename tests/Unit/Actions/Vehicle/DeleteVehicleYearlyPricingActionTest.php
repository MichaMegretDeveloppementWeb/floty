<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Vehicle;

use App\Actions\Vehicle\DeleteVehicleYearlyPricingAction;
use App\Exceptions\Vehicle\VehicleYearlyPricingNotFoundException;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeleteVehicleYearlyPricingActionTest extends TestCase
{
    use RefreshDatabase;

    private DeleteVehicleYearlyPricingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(DeleteVehicleYearlyPricingAction::class);
    }

    #[Test]
    public function supprime_le_pricing_existant(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->create();

        $this->action->execute($vehicle->id, 2024);

        $this->assertDatabaseMissing('vehicle_yearly_pricings', [
            'vehicle_id' => $vehicle->id,
            'year' => 2024,
        ]);
    }

    #[Test]
    public function leve_exception_si_aucun_pricing_pour_l_annee(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->expectException(VehicleYearlyPricingNotFoundException::class);

        $this->action->execute($vehicle->id, 2024);
    }

    #[Test]
    public function ne_supprime_pas_les_pricings_d_autres_annees(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()->for($vehicle)->forYear(2023)->create();
        VehicleYearlyPricing::factory()->for($vehicle)->forYear(2024)->create();
        VehicleYearlyPricing::factory()->for($vehicle)->forYear(2025)->create();

        $this->action->execute($vehicle->id, 2024);

        $this->assertSame(2, VehicleYearlyPricing::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('vehicle_yearly_pricings', ['vehicle_id' => $vehicle->id, 'year' => 2023]);
        $this->assertDatabaseHas('vehicle_yearly_pricings', ['vehicle_id' => $vehicle->id, 'year' => 2025]);
    }
}
