<?php

declare(strict_types=1);

namespace App\Strategies\VehicleRegistryLookup;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
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
    public function driver(?RegistryLookupProvider $provider = null): VehicleRegistryLookupInterface
    {
        if ($provider === null) {
            if (! $this->isEnabled()) {
                throw RegistryLookupUnavailableException::featureDisabled();
            }

            $provider = $this->configuredProvider();

            if ($provider === null) {
                throw RegistryLookupUnavailableException::noProviderConfigured(
                    (string) $this->config->get('vehicle-registry.default'),
                );
            }
        }

        $this->guardProvider($provider);

        return $this->build($provider);
    }

    /**
     * Whether the feature is currently usable (enabled, configured, implemented, allowed in env).
     */
    public function isAvailable(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $provider = $this->configuredProvider();
        if ($provider === null) {
            return false;
        }

        return $this->isProviderImplemented($provider)
            && $this->isProviderAllowedInEnvironment($provider);
    }

    /**
     * Provider currently set in config, or null if missing or invalid.
     */
    public function configuredProvider(): ?RegistryLookupProvider
    {
        $raw = $this->config->get('vehicle-registry.default');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return RegistryLookupProvider::tryFrom($raw);
    }

    /**
     * Whether the feature flag is on.
     */
    private function isEnabled(): bool
    {
        return (bool) $this->config->get('vehicle-registry.enabled', false);
    }

    /**
     * Whether a concrete strategy class exists for the provider.
     *
     * Flip the AaaData branch to true once AaaDataVehicleRegistryLookupStrategy is implemented.
     */
    private function isProviderImplemented(RegistryLookupProvider $provider): bool
    {
        return match ($provider) {
            RegistryLookupProvider::Fake => true,
            RegistryLookupProvider::AaaData => false,
        };
    }

    /**
     * Whether the provider is allowed in the current environment.
     *
     * Fake is forbidden in production to prevent accidental deployment.
     */
    private function isProviderAllowedInEnvironment(RegistryLookupProvider $provider): bool
    {
        return match ($provider) {
            RegistryLookupProvider::Fake => ! $this->app->isProduction(),
            RegistryLookupProvider::AaaData => true,
        };
    }

    /**
     * Ensure the provider is implemented and allowed before building.
     *
     * @throws RegistryLookupUnavailableException
     */
    private function guardProvider(RegistryLookupProvider $provider): void
    {
        if (! $this->isProviderImplemented($provider)) {
            throw RegistryLookupUnavailableException::providerNotImplemented($provider);
        }

        if (! $this->isProviderAllowedInEnvironment($provider)) {
            throw RegistryLookupUnavailableException::providerRefusedInEnvironment(
                $provider,
                $this->app->environment(),
            );
        }
    }

    /**
     * Instantiate the concrete strategy bound to the provider.
     *
     * @throws RegistryLookupUnavailableException
     */
    private function build(RegistryLookupProvider $provider): VehicleRegistryLookupInterface
    {
        return match ($provider) {
            RegistryLookupProvider::Fake => $this->container->make(FakeVehicleRegistryLookupStrategy::class),
            RegistryLookupProvider::AaaData => throw RegistryLookupUnavailableException::providerNotImplemented($provider),
        };
    }
}
