<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Strategies\VehicleRegistryLookup\VehicleRegistryLookupStrategyFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Bindings du Strategy pattern de lookup véhicule.
 *
 * Tout consommateur qui type-hint
 * {@see VehicleRegistryLookupInterface} reçoit la strategy active
 * selon `config('vehicle-registry.default')`. La factory garantit que
 * la strategy retournée est implémentée et autorisée dans
 * l'environnement courant ; sinon elle lève
 * {@see App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException}.
 */
final class VehicleRegistryLookupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VehicleRegistryLookupStrategyFactory::class);

        $this->app->bind(
            VehicleRegistryLookupInterface::class,
            fn ($app) => $app->make(VehicleRegistryLookupStrategyFactory::class)->make(),
        );
    }
}
