<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleRegistryLookupEnabledSharedPropTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function shared_prop_est_false_quand_feature_desactivee(): void
    {
        config(['vehicle-registry.enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicleRegistryLookupEnabled', false));
    }

    #[Test]
    public function shared_prop_est_false_quand_driver_aaa_data_non_implemente(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'aaa_data']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicleRegistryLookupEnabled', false));
    }

    #[Test]
    public function shared_prop_est_true_quand_fake_actif_hors_production(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicleRegistryLookupEnabled', true));
    }
}
