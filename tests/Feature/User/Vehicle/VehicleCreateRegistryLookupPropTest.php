<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleCreateRegistryLookupPropTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function page_prop_est_false_quand_feature_desactivee(): void
    {
        config(['vehicle-registry.enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('registryLookupEnabled', false));
    }

    #[Test]
    public function page_prop_est_false_quand_driver_aaa_data_non_implemente(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'aaa_data']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('registryLookupEnabled', false));
    }

    #[Test]
    public function page_prop_est_true_quand_fake_actif_hors_production(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('registryLookupEnabled', true));
    }

    #[Test]
    public function autres_pages_ne_recoivent_pas_la_prop(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('registryLookupEnabled'));
    }
}
