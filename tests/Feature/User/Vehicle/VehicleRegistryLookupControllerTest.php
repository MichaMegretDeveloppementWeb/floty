<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleRegistryLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function refuse_quand_la_feature_est_desactivee(): void
    {
        config(['vehicle-registry.enabled' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/vehicles/registry-lookup', [
                'license_plate' => 'AA-123-AA',
            ]);

        $response->assertStatus(503);
        $response->assertJsonPath('error.code', 'unavailable');
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    #[Test]
    public function refuse_quand_aucun_driver_n_est_configure(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => null]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/vehicles/registry-lookup', [
                'license_plate' => 'AA-123-AA',
            ]);

        $response->assertStatus(503);
        $response->assertJsonPath('error.code', 'unavailable');
    }

    #[Test]
    public function refuse_aaa_data_tant_que_la_strategy_n_est_pas_implementee(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'aaa_data']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/vehicles/registry-lookup', [
                'license_plate' => 'AA-123-AA',
            ]);

        $response->assertStatus(503);
        $response->assertJsonPath('error.code', 'unavailable');
    }

    #[Test]
    public function renvoie_le_dto_quand_fake_est_actif_et_plaque_de_fixture(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/vehicles/registry-lookup', [
                'license_plate' => 'AA-123-AA',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('licensePlate', 'AA123AA');
        $response->assertJsonPath('brand', 'Peugeot');
        $response->assertJsonPath('receptionCategory', 'M1');
        $response->assertJsonPath('energySource', 'gasoline');
        $response->assertJsonPath('sourceDriver', 'fake');
    }

    #[Test]
    public function renvoie_404_quand_la_plaque_est_inconnue_du_provider(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/vehicles/registry-lookup', [
                'license_plate' => 'ZZ-999-ZZ',
            ]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'not_found');
        $response->assertJsonPath('error.message', fn (string $msg) => str_contains($msg, 'introuvable'));
    }

    #[Test]
    public function valide_la_plaque_avant_appel_provider(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/vehicles/registry-lookup', [
                'license_plate' => 'AB',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['license_plate']);
    }

    #[Test]
    public function refuse_un_utilisateur_non_authentifie(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $response = $this->postJson('/app/vehicles/registry-lookup', [
            'license_plate' => 'AA-123-AA',
        ]);

        $response->assertStatus(401);
    }
}
