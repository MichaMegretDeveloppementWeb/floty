<?php

use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
