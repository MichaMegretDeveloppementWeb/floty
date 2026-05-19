<?php

declare(strict_types=1);

namespace App\Managers\VehicleRegistryLookup;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Managers\VehicleRegistryLookup\Drivers\FakeVehicleRegistryLookupDriver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;

/**
 * Driver-Manager for the vehicle registry lookup feature, mirroring
 * Laravel's MailManager / CacheManager / FilesystemManager pattern.
 * A single driver is active per installation, selected via config.
 */
final readonly class VehicleRegistryLookupManager
{
    public function __construct(
        private Container $container,
        private ConfigRepository $config,
        private Application $app,
    ) {}

    /**
     * Resolve the active driver, or throw if none can be served.
     *
     * @throws RegistryLookupUnavailableException
     */
    public function driver(?RegistryLookupDriver $driver = null): VehicleRegistryLookupInterface
    {
        if ($driver === null) {
            if (! $this->isEnabled()) {
                throw RegistryLookupUnavailableException::featureDisabled();
            }

            $driver = $this->configuredDriver();

            if ($driver === null) {
                throw RegistryLookupUnavailableException::noDriverConfigured(
                    (string) $this->config->get('vehicle-registry.default'),
                );
            }
        }

        $this->guardDriver($driver);

        return $this->build($driver);
    }

    /**
     * Whether the feature is currently usable (enabled, configured, implemented, allowed in env).
     */
    public function isAvailable(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $driver = $this->configuredDriver();
        if ($driver === null) {
            return false;
        }

        return $this->isDriverImplemented($driver)
            && $this->isDriverAllowedInEnvironment($driver);
    }

    /**
     * Driver currently set in config, or null if missing or invalid.
     */
    public function configuredDriver(): ?RegistryLookupDriver
    {
        $raw = $this->config->get('vehicle-registry.default');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return RegistryLookupDriver::tryFrom($raw);
    }

    /**
     * Whether the feature flag is on.
     */
    private function isEnabled(): bool
    {
        return (bool) $this->config->get('vehicle-registry.enabled', false);
    }

    /**
     * Whether a concrete implementation class exists for the driver.
     *
     * Flip the AaaData branch to true once AaaDataVehicleRegistryLookupDriver is implemented.
     */
    private function isDriverImplemented(RegistryLookupDriver $driver): bool
    {
        return match ($driver) {
            RegistryLookupDriver::Fake => true,
            RegistryLookupDriver::AaaData => false,
        };
    }

    /**
     * Whether the driver is allowed in the current environment.
     *
     * Fake is forbidden in production to prevent accidental deployment.
     */
    private function isDriverAllowedInEnvironment(RegistryLookupDriver $driver): bool
    {
        return match ($driver) {
            RegistryLookupDriver::Fake => ! $this->app->isProduction(),
            RegistryLookupDriver::AaaData => true,
        };
    }

    /**
     * Ensure the driver is implemented and allowed before building.
     *
     * @throws RegistryLookupUnavailableException
     */
    private function guardDriver(RegistryLookupDriver $driver): void
    {
        if (! $this->isDriverImplemented($driver)) {
            throw RegistryLookupUnavailableException::driverNotImplemented($driver);
        }

        if (! $this->isDriverAllowedInEnvironment($driver)) {
            throw RegistryLookupUnavailableException::driverRefusedInEnvironment(
                $driver,
                $this->app->environment(),
            );
        }
    }

    /**
     * Instantiate the concrete implementation bound to the driver.
     *
     * @throws RegistryLookupUnavailableException
     */
    private function build(RegistryLookupDriver $driver): VehicleRegistryLookupInterface
    {
        return match ($driver) {
            RegistryLookupDriver::Fake => $this->container->make(FakeVehicleRegistryLookupDriver::class),
            RegistryLookupDriver::AaaData => throw RegistryLookupUnavailableException::driverNotImplemented($driver),
        };
    }
}
