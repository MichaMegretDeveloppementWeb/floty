<?php

declare(strict_types=1);

use App\Fiscal\Year2024\Year2024Boot;
use App\Fiscal\Year2025\Year2025Boot;
use App\Fiscal\Year2026\Year2026Boot;

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
            Year2025Boot::class,
            Year2026Boot::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Déclarations fiscales (annexes PDF)
    |--------------------------------------------------------------------------
    |
    | Disque Storage utilisé par {@see App\Services\Pdf\DeclarationPdfStorage}
    | pour persister immuablement les annexes PDF des déclarations fiscales
    | (ADR-0008/ADR-0015 § 5.1 + D8). Doit être un disque privé · les PDF
    | contiennent des données fiscales sensibles non destinées au public.
    |
    | Lot 5 D10 (F-19-016) · variable d'env `DECLARATIONS_PDF_DISK` · permet
    | de basculer sur S3/GCS en prod sans toucher au code. Default `local`
    | reste compatible avec le développement et la stack Floty actuelle
    | (ADR `project_infra_no_queue_no_cron`, pas d'object storage actif).
    */
    'declarations' => [
        'pdf_storage_disk' => env('DECLARATIONS_PDF_DISK', 'local'),
    ],

];
