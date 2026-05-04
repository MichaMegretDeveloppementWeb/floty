<?php

declare(strict_types=1);
use App\Fiscal\Year2024\Year2024Boot;

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

        /*
        |----------------------------------------------------------------
        | Catalogues de règles par année (chantier ζ)
        |----------------------------------------------------------------
        |
        | Liste des classes implémentant `App\Fiscal\Contracts\FiscalYearBoot`,
        | une par année fiscale supportée. `App\Providers\FiscalServiceProvider`
        | les itère au boot pour peupler le `FiscalRuleRegistry`.
        |
        | Pour ajouter une année :
        |   1. Créer `app/Fiscal/Year{YYYY}/Year{YYYY}Boot.php`.
        |   2. Lister les classes de règles dans sa méthode `rules()`.
        |   3. Ajouter la classe ici et au tableau `available_years`.
        |
        | Aucune modification du provider n'est requise.
        |
        | Cf. `project-management/taxes-rules/_adding-a-new-year.md`.
        */
        'year_boots' => [
            Year2024Boot::class,
        ],
    ],

];
