<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleFiscalCharacteristicsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_modifie_une_vfc_isolee_depuis_la_modale_historique(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'seats_count' => 5,
        ]);

        $payload = $this->buildVfcPayload($vfc, ['seats_count' => 9]);

        $this->actingAs($user)
            ->patch("/app/vehicle-fiscal-characteristics/{$vfc->id}", $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $vfc->id,
            'seats_count' => 9,
        ]);
    }

    #[Test]
    public function destroy_avec_extend_next_supprime_la_vfc_et_etend_la_suivante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $oldest = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->delete(
                "/app/vehicle-fiscal-characteristics/{$oldest->id}",
                ['extension_strategy' => 'extend_next'],
            )
            ->assertRedirect();

        $this->assertDatabaseMissing('vehicle_fiscal_characteristics', [
            'id' => $oldest->id,
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_from' => '2022-01-01',
        ]);
    }

    #[Test]
    public function destroy_avec_extend_previous_supprime_la_vfc_et_etend_la_precedente(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $oldest = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->delete(
                "/app/vehicle-fiscal-characteristics/{$current->id}",
                ['extension_strategy' => 'extend_previous'],
            )
            ->assertRedirect();

        $this->assertDatabaseMissing('vehicle_fiscal_characteristics', [
            'id' => $current->id,
        ]);
        // La précédente reprend le rôle de courante (effective_to = null).
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $oldest->id,
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function destroy_refuse_si_unique_version(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->delete(
                "/app/vehicle-fiscal-characteristics/{$vfc->id}",
                ['extension_strategy' => 'extend_previous'],
            )
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');

        // VFC toujours présente.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $vfc->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildVfcPayload(VehicleFiscalCharacteristics $vfc, array $overrides = []): array
    {
        return array_merge([
            'effective_from' => $vfc->effective_from?->format('Y-m-d'),
            'effective_to' => $vfc->effective_to?->format('Y-m-d'),
            'reception_category' => $vfc->reception_category->value,
            'vehicle_user_type' => $vfc->vehicle_user_type->value,
            'body_type' => $vfc->body_type->value,
            'seats_count' => $vfc->seats_count,
            'energy_source' => $vfc->energy_source->value,
            'euro_standard' => $vfc->euro_standard?->value,
            'pollutant_category' => $vfc->pollutant_category->value,
            'homologation_method' => $vfc->homologation_method->value,
            'co2_wltp' => $vfc->co2_wltp,
            'co2_nedc' => $vfc->co2_nedc,
            'taxable_horsepower' => $vfc->taxable_horsepower,
            'change_reason' => $vfc->change_reason->value === 'initial_creation'
                ? 'recharacterization'
                : $vfc->change_reason->value,
            'change_note' => $vfc->change_note,
        ], $overrides);
    }
}
