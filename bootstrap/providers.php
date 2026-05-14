<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FiscalServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    RepositoryServiceProvider::class,
    FiscalServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
