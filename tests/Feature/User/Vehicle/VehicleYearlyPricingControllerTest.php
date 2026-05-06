<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleYearlyPricingControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_cree_un_pricing_et_retourne_un_toast_success(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/yearly-pricings", [
                'year' => 2024,
                'daily_rate_cents' => 10_000,
                'weekly_rate_cents' => 60_000,
                'monthly_rate_cents' => 200_000,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseHas('vehicle_yearly_pricings', [
            'vehicle_id' => $vehicle->id,
            'year' => 2024,
            'daily_rate_cents' => 10_000,
        ]);
    }

    #[Test]
    public function store_avec_tarif_negatif_retourne_422(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/yearly-pricings", [
                'year' => 2024,
                'daily_rate_cents' => -100,
                'weekly_rate_cents' => 60_000,
                'monthly_rate_cents' => 200_000,
            ])
            ->assertSessionHasErrors(['daily_rate_cents']);
    }

    #[Test]
    public function store_avec_annee_hors_plage_retourne_422(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/yearly-pricings", [
                'year' => 2019, // < 2020 borne min
                'daily_rate_cents' => 10_000,
                'weekly_rate_cents' => 60_000,
                'monthly_rate_cents' => 200_000,
            ])
            ->assertSessionHasErrors(['year']);
    }

    #[Test]
    public function store_sur_meme_couple_vehicule_annee_met_a_jour_le_pricing_existant(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $existing = VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(8_000, 50_000, 180_000)
            ->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/yearly-pricings", [
                'year' => 2024,
                'daily_rate_cents' => 12_000,
                'weekly_rate_cents' => 70_000,
                'monthly_rate_cents' => 220_000,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        // Une seule ligne et nouvelles valeurs persistées
        $this->assertSame(1, VehicleYearlyPricing::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('vehicle_yearly_pricings', [
            'id' => $existing->id,
            'daily_rate_cents' => 12_000,
        ]);
    }

    #[Test]
    public function destroy_supprime_le_pricing_et_retourne_un_toast_success(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->create();

        $this->actingAs($user)
            ->delete("/app/vehicles/{$vehicle->id}/yearly-pricings/2024")
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseMissing('vehicle_yearly_pricings', [
            'vehicle_id' => $vehicle->id,
            'year' => 2024,
        ]);
    }

    #[Test]
    public function destroy_sur_pricing_inexistant_retourne_un_toast_error(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->delete("/app/vehicles/{$vehicle->id}/yearly-pricings/2024")
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');
    }
}
