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
 * Factory du Strategy pattern de lookup véhicule.
 *
 * Triple verrou de disponibilité (cf. {@see self::isAvailable()}) :
 *   1. Feature flag global `vehicle-registry.enabled`.
 *   2. Driver configuré valide (`vehicle-registry.default` ∈ enum).
 *   3. Strategy effectivement implémentée pour le driver (la branche
 *      match `case AaaData` renvoie `false` tant que la strategy
 *      production n'est pas écrite ; PHP exhaustive match nous
 *      forcera à mettre à jour ce point quand on l'ajoutera).
 *   4. Provider autorisé dans l'environnement courant (Fake refusé en
 *      production · garde-fou anti-déploiement accidentel).
 *
 * Cette factory est référencée :
 *   - par le ServiceProvider qui bind l'interface vers le driver actif.
 *   - par le controller qui Gate l'endpoint sur `isAvailable()`.
 *   - par le middleware Inertia qui partage `vehicleRegistryLookupEnabled`
 *     côté Vue pour cacher le bouton si indisponible.
 */
final readonly class VehicleRegistryLookupStrategyFactory
{
    public function __construct(
        private Container $container,
        private ConfigRepository $config,
        private Application $app,
    ) {}

    /**
     * Résout la strategy active. Si `$provider` est omis, lit
     * `vehicle-registry.default` après validation complète via
     * {@see self::isAvailable()}.
     *
     * @throws RegistryLookupUnavailableException si la feature est
     *                                            indisponible ou si
     *                                            la strategy demandée
     *                                            n'est pas implémentée
     *                                            / autorisée
     */
    public function make(?RegistryLookupProvider $provider = null): VehicleRegistryLookupInterface
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
     * Vérifie si la feature est disponible (UI + backend). Utilisé par
     * le middleware Inertia (shared prop) et le controller (Gate).
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
     * Provider actuellement configuré (sans contrôle de disponibilité).
     * Renvoie `null` si la config est vide ou pointe sur une valeur
     * absente de l'enum.
     */
    public function configuredProvider(): ?RegistryLookupProvider
    {
        $raw = $this->config->get('vehicle-registry.default');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return RegistryLookupProvider::tryFrom($raw);
    }

    private function isEnabled(): bool
    {
        return (bool) $this->config->get('vehicle-registry.enabled', false);
    }

    /**
     * Source unique de vérité pour « cette strategy est-elle écrite ? ».
     *
     * **Important** · quand `AaaDataVehicleRegistryLookupStrategy`
     * sera implémentée, ne pas oublier de basculer la branche
     * `RegistryLookupProvider::AaaData` à `true` ici.
     */
    private function isProviderImplemented(RegistryLookupProvider $provider): bool
    {
        return match ($provider) {
            RegistryLookupProvider::Fake => true,
            RegistryLookupProvider::AaaData => false,
        };
    }

    /**
     * Garde-fous d'environnement. Le driver Fake est strictement
     * interdit en production · il n'est destiné qu'aux tests et au
     * dev local.
     */
    private function isProviderAllowedInEnvironment(RegistryLookupProvider $provider): bool
    {
        return match ($provider) {
            RegistryLookupProvider::Fake => ! $this->app->isProduction(),
            RegistryLookupProvider::AaaData => true,
        };
    }

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

    private function build(RegistryLookupProvider $provider): VehicleRegistryLookupInterface
    {
        return match ($provider) {
            RegistryLookupProvider::Fake => $this->container->make(FakeVehicleRegistryLookupStrategy::class),
            RegistryLookupProvider::AaaData => throw RegistryLookupUnavailableException::providerNotImplemented($provider),
        };
    }
}
