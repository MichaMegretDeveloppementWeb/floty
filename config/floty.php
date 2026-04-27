<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Années fiscales disponibles
    |--------------------------------------------------------------------------
    |
    | Liste statique, définie par déploiement, des années fiscales pour
    | lesquelles le moteur fiscal est implémenté (ie. dont les règles sont
    | enregistrées dans le `FiscalRuleRegistry` via `FiscalServiceProvider`).
    |
    | L'année **active** côté utilisateur est portée par la session via
    | `App\Fiscal\Resolver\FiscalYearResolver` — pas par cette config,
    | qui était partagée entre tous les utilisateurs (effet de bord
    | global lors d'un changement d'année).
    |
    | Convention : la première année du tableau est la valeur de
    | fallback quand aucune année n'est posée en session (cas typique
    | du premier accès à l'application).
    */

    'fiscal' => [
        'available_years' => [2024],
    ],

];
