<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Vehicle Registry Lookup · configuration
|--------------------------------------------------------------------------
|
| Pilote la feature « pré-remplissage du formulaire véhicule depuis la
| plaque d'immatriculation » via le pattern Strategy
| (cf. `App\Strategies\VehicleRegistryLookup`).
|
| Disponibilité réelle de la feature résolue à l'exécution par
| {@see App\Strategies\VehicleRegistryLookup\VehicleRegistryLookupStrategyFactory::isAvailable()}
| · combine `enabled` + `default` driver + check d'implémentation effective
| de la strategy correspondante + interdiction du Fake en production.
|
| Tant qu'aucun provider réel n'est implémenté, la feature reste
| invisible côté UI (Inertia shared prop `vehicleRegistryLookupEnabled`
| · false) ET le controller renvoie une erreur explicite si un client
| bypass la non-présence du bouton.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Doit être `true` pour que la feature soit même considérée. Par
    | défaut `false` · couvre le cas « variable d'environnement oubliée
    | en prod ».
    |
    */
    'enabled' => (bool) env('VEHICLE_REGISTRY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Driver par défaut
    |--------------------------------------------------------------------------
    |
    | Doit matcher une valeur de
    | {@see App\Enums\VehicleRegistryLookup\RegistryLookupProvider} ·
    | `fake` (dev/tests uniquement, refusé en prod) ou `aaa_data` (prod ·
    | provider à implémenter après signature contractuelle).
    |
    */
    'default' => env('VEHICLE_REGISTRY_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Configuration par provider
    |--------------------------------------------------------------------------
    */
    'providers' => [
        /*
         | AAA Data · provider production (à implémenter après signature
         | du contrat fournisseur · cf. brief client + workflow
         | fournisseur dans
         | `project-management/specifications-fonctionnelles/vehicle-registry-lookup/`).
         */
        'aaa_data' => [
            'base_url' => env('AAA_DATA_BASE_URL'),
            'api_key' => env('AAA_DATA_API_KEY'),
            'timeout_seconds' => (int) env('AAA_DATA_TIMEOUT_SECONDS', 10),
        ],

        /*
         | Fake · stub utilisé en tests + dev local pour valider l'UX et
         | l'intégration avant disponibilité d'AAA Data. Les fixtures
         | peuvent être surchargées par test via `config(['vehicle-registry.providers.fake.fixtures' => [...]])`.
         */
        'fake' => [
            'fixtures' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle de l'endpoint de lookup
    |--------------------------------------------------------------------------
    |
    | Garde-fou anti-abus côté controller (utilisateur authentifié) ·
    | format `N,M` · N requêtes par M minute(s).
    |
    */
    'throttle' => env('VEHICLE_REGISTRY_THROTTLE', '20,1'),
];
