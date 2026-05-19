<?php

declare(strict_types=1);

namespace Tests\Unit\Strategies\VehicleRegistryLookup;

use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Strategies\VehicleRegistryLookup\FakeVehicleRegistryLookupStrategy;
use App\Strategies\VehicleRegistryLookup\VehicleRegistryLookupManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleRegistryLookupManagerTest extends TestCase
{
    #[Test]
    public function is_available_renvoie_false_quand_feature_desactivee(): void
    {
        config(['vehicle-registry.enabled' => false]);
        config(['vehicle-registry.default' => 'fake']);

        $this->assertFalse($this->makeManager()->isAvailable());
    }

    #[Test]
    public function is_available_renvoie_false_quand_driver_non_configure(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => null]);

        $this->assertFalse($this->makeManager()->isAvailable());
    }

    #[Test]
    public function is_available_renvoie_false_quand_driver_invalide(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'foobar']);

        $this->assertFalse($this->makeManager()->isAvailable());
    }

    #[Test]
    public function is_available_renvoie_false_quand_strategy_non_implementee(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'aaa_data']);

        $this->assertFalse($this->makeManager()->isAvailable());
    }

    #[Test]
    public function is_available_renvoie_true_quand_fake_configure_hors_production(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $this->assertFalse($this->app->isProduction());
        $this->assertTrue($this->makeManager()->isAvailable());
    }

    #[Test]
    public function driver_avec_fake_retourne_fake_vehicle_registry_lookup_strategy(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'fake']);

        $strategy = $this->makeManager()->driver();

        $this->assertInstanceOf(FakeVehicleRegistryLookupStrategy::class, $strategy);
        $this->assertSame(RegistryLookupProvider::Fake, $strategy->provider());
    }

    #[Test]
    public function driver_leve_unavailable_quand_feature_desactivee(): void
    {
        config(['vehicle-registry.enabled' => false]);
        config(['vehicle-registry.default' => 'fake']);

        $this->expectException(RegistryLookupUnavailableException::class);
        $this->expectExceptionMessage('disabled');

        $this->makeManager()->driver();
    }

    #[Test]
    public function driver_leve_unavailable_quand_strategy_aaa_data_non_implementee(): void
    {
        config(['vehicle-registry.enabled' => true]);
        config(['vehicle-registry.default' => 'aaa_data']);

        $this->expectException(RegistryLookupUnavailableException::class);
        $this->expectExceptionMessage('not implemented yet');

        $this->makeManager()->driver();
    }

    #[Test]
    public function driver_force_via_parametre_court_circuite_la_config_mais_pas_l_implementation(): void
    {
        config(['vehicle-registry.enabled' => false]);

        $strategy = $this->makeManager()->driver(RegistryLookupProvider::Fake);

        $this->assertInstanceOf(FakeVehicleRegistryLookupStrategy::class, $strategy);
    }

    #[Test]
    public function driver_force_aaa_data_explicite_leve_quand_meme_unavailable(): void
    {
        $this->expectException(RegistryLookupUnavailableException::class);

        $this->makeManager()->driver(RegistryLookupProvider::AaaData);
    }

    /**
     * Resolve a manager instance through the container.
     */
    private function makeManager(): VehicleRegistryLookupManager
    {
        return $this->app->make(VehicleRegistryLookupManager::class);
    }
}
