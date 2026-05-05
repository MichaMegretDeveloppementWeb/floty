<?php

declare(strict_types=1);
use App\Fiscal\Year2024\Year2024Boot;

return [

    /*
    |--------------------------------------------------------------------------
    | Catalogues de règles par année
    |--------------------------------------------------------------------------
    |
    | Liste des classes implémentant `App\Fiscal\Contracts\FiscalYearBoot`,
    | une par année fiscale supportée. `App\Providers\FiscalServiceProvider`
    | les itère au boot pour peupler le `FiscalRuleRegistry`.
    |
    | **Source d'autorité unique** des années connues du moteur fiscal :
    | `FiscalRuleRegistry::registeredYears()` (alimenté par les boots ici).
    | L'ancienne config `fiscal.available_years` (qui doublait cette
    | source) a été supprimée chantier η Phase 5.
    |
    | Pour ajouter une année :
    |   1. Créer `app/Fiscal/Year{YYYY}/Year{YYYY}Boot.php`.
    |   2. Lister les classes de règles dans sa méthode `rules()`.
    |   3. Ajouter la classe au tableau `year_boots` ci-dessous.
    |
    | Aucune modification du provider n'est requise.
    |
    | Cf. `project-management/taxes-rules/_adding-a-new-year.md`.
    */

    'fiscal' => [
        'year_boots' => [
            Year2024Boot::class,
        ],
    ],

];
