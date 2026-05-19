<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Feature toggle
    |--------------------------------------------------------------------------
    |
    | Master switch for the vehicle registry lookup feature. Defaults to
    | false so that no UI is exposed unless explicitly enabled.
    |
    */
    'enabled' => (bool) env('VEHICLE_REGISTRY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    |
    | Must match a case of App\Enums\VehicleRegistryLookup\RegistryLookupDriver.
    | Allowed values: "fake" (non-production only), "api_plaque" or "aaa_data".
    |
    */
    'default' => env('VEHICLE_REGISTRY_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Provider configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'aaa_data' => [
            'base_url' => env('AAA_DATA_BASE_URL'),
            'api_key' => env('AAA_DATA_API_KEY'),
            'timeout_seconds' => (int) env('AAA_DATA_TIMEOUT_SECONDS', 10),
        ],

        'api_plaque' => [
            'base_url' => env(
                'API_PLAQUE_BASE_URL',
                'https://api-de-plaque-d-immatriculation-france.p.rapidapi.com',
            ),
            'host' => env(
                'API_PLAQUE_HOST',
                'api-de-plaque-d-immatriculation-france.p.rapidapi.com',
            ),
            'api_key' => env('API_PLAQUE_KEY'),
            'timeout_seconds' => (int) env('API_PLAQUE_TIMEOUT_SECONDS', 10),
        ],

        'fake' => [
            'fixtures' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lookup endpoint throttle
    |--------------------------------------------------------------------------
    |
    | Format: "N,M" · N requests per M minute(s).
    |
    */
    'throttle' => env('VEHICLE_REGISTRY_THROTTLE', '20,1'),
];
