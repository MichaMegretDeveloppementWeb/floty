<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Strategies\VehicleRegistryLookup\VehicleRegistryLookupStrategyFactory;
use Illuminate\Support\ServiceProvider;

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
