<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\VehicleRegistryLookup\VehicleRegistryLookupInterface;
use App\Managers\VehicleRegistryLookup\VehicleRegistryLookupManager;
use Illuminate\Support\ServiceProvider;

final class VehicleRegistryLookupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VehicleRegistryLookupManager::class);

        $this->app->bind(
            VehicleRegistryLookupInterface::class,
            fn ($app) => $app->make(VehicleRegistryLookupManager::class)->driver(),
        );
    }
}
