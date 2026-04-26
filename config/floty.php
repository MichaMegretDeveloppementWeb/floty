<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Année fiscale active
    |--------------------------------------------------------------------------
    |
    | Source de vérité unique pour l'année fiscale du MVP. Tant qu'une
    | seule année de règles fiscales est codée (2024), cette valeur est
    | figée et propagée :
    |   - dans `HandleInertiaRequests` → shared props `fiscal.currentYear`
    |   - dans tous les controllers via `config('floty.fiscal.current_year')`
    |   - dans le YearSelector côté front (min = max → flèches désactivées)
    |
    | Quand une nouvelle année de règles sera ajoutée, on basculera ici
    | sur un tableau d'années disponibles et on permettra la sélection
    | depuis la TopBar.
    */

    'fiscal' => [
        'current_year' => 2024,
        'available_years' => [2024],
    ],

];
