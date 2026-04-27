<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FiscalServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    FiscalServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
