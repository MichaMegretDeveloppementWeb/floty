<?php

declare(strict_types=1);

use App\Fiscal\Year2024\Year2024Boot;
use App\Fiscal\Year2025\Year2025Boot;
use App\Fiscal\Year2026\Year2026Boot;

return [

    /*
    |--------------------------------------------------------------------------
    | Fiscal year boots
    |--------------------------------------------------------------------------
    |
    | Classes implementing `App\Fiscal\Contracts\FiscalYearBoot`, one per
    | supported fiscal year. `App\Providers\FiscalServiceProvider` iterates
    | over them at boot time to populate the `FiscalRuleRegistry`.
    |
    | This list is the single source of authority for the years known to the
    | fiscal engine: `FiscalRuleRegistry::registeredYears()`.
    |
    | To add a new year:
    |   1. Create `app/Fiscal/Year{YYYY}/Year{YYYY}Boot.php`.
    |   2. List its rule classes in the `rules()` method.
    |   3. Append the boot class to `year_boots` below.
    |
    | See `project-management/taxes-rules/_adding-a-new-year.md`.
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
    | Declarations (PDF annexes)
    |--------------------------------------------------------------------------
    |
    | Storage disk used by `App\Services\Pdf\DeclarationPdfStorage` to
    | immutably persist declaration PDF annexes (ADR-0008 / ADR-0015 § 5.1).
    | The disk must be private because PDFs contain sensitive fiscal data.
    |
    | The `DECLARATIONS_PDF_DISK` env variable lets production switch to
    | S3 / GCS without code changes. The default `local` matches the current
    | Floty stack (no object storage in use).
    */

    'declarations' => [
        'pdf_storage_disk' => env('DECLARATIONS_PDF_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler heartbeat (Chantier B / B3)
    |--------------------------------------------------------------------------
    |
    | The control-reminder cron updates a heartbeat hourly. If the last run is
    | older than this many minutes, the UI raises a "scheduler dead" warning so
    | a stopped cron is caught within hours (ADR-0008). Hourly ticks + a 3h
    | threshold tolerate one or two missed ticks without false positives.
    */

    'scheduler' => [
        'heartbeat_stale_minutes' => (int) env('SCHEDULER_HEARTBEAT_STALE_MINUTES', 180),
    ],

];
