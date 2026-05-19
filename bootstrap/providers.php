<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FiscalServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use App\Providers\VehicleRegistryLookupServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    RepositoryServiceProvider::class,
    FiscalServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
    VehicleRegistryLookupServiceProvider::class,
];
