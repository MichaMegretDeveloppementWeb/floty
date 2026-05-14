<?php

declare(strict_types=1);

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        /*
        |----------------------------------------------------------------------
        | Canaux thématiques Floty
        |----------------------------------------------------------------------
        |
        | Cf. `project-management/implementation-rules/gestion-erreurs.md`
        | § « Canaux thématiques Floty » + plan-remédiation Vague 1 Lot 2 D2
        | (F-33-002 + F-30-004 + F-19-009).
        |
        | Rétentions (par criticité régulatoire) ·
        |   - 365 j  declarations, invoices  (pièces justificatives officielles)
        |   -  90 j  fiscal, contracts       (audit moteur fiscal + grade fiscal)
        |   -  30 j  auth, vehicles, companies, drivers, unavailabilities, pdf
        |   -   7 j  cache                   (très volumineux, peu utile au-delà)
        |
        | Levels · chaque canal a sa propre variable `*_LOG_LEVEL` (default
        | `notice`). En prod un `LOG_LEVEL=warning` global ne doit PAS
        | filtrer silencieusement les events d'audit fonctionnel (success,
        | mutations, décisions) qui sont émis en `notice`.
        */

        'auth' => [
            'driver' => 'daily',
            'path' => storage_path('logs/auth.log'),
            // Le canal reçoit login.success + login.failed (notice) en plus
            // des login.lockout (warning). Cf. ADR-0011 § 3 + Lot 1 D2.
            'level' => env('AUTH_LOG_LEVEL', 'notice'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'fiscal' => [
            'driver' => 'daily',
            'path' => storage_path('logs/fiscal.log'),
            'level' => env('FISCAL_LOG_LEVEL', 'notice'),
            'days' => 90,
            'replace_placeholders' => true,
        ],

        'declarations' => [
            'driver' => 'daily',
            'path' => storage_path('logs/declarations.log'),
            'level' => env('DECLARATIONS_LOG_LEVEL', 'notice'),
            'days' => 365,
            'replace_placeholders' => true,
        ],

        'companies' => [
            'driver' => 'daily',
            'path' => storage_path('logs/companies.log'),
            'level' => env('COMPANIES_LOG_LEVEL', 'notice'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'vehicles' => [
            'driver' => 'daily',
            'path' => storage_path('logs/vehicles.log'),
            'level' => env('VEHICLES_LOG_LEVEL', 'notice'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'drivers' => [
            'driver' => 'daily',
            'path' => storage_path('logs/drivers.log'),
            'level' => env('DRIVERS_LOG_LEVEL', 'notice'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'contracts' => [
            'driver' => 'daily',
            'path' => storage_path('logs/contracts.log'),
            'level' => env('CONTRACTS_LOG_LEVEL', 'notice'),
            'days' => 90,
            'replace_placeholders' => true,
        ],

        'unavailabilities' => [
            'driver' => 'daily',
            'path' => storage_path('logs/unavailabilities.log'),
            'level' => env('UNAVAILABILITIES_LOG_LEVEL', 'notice'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'invoices' => [
            'driver' => 'daily',
            'path' => storage_path('logs/invoices.log'),
            'level' => env('INVOICES_LOG_LEVEL', 'notice'),
            'days' => 365,
            'replace_placeholders' => true,
        ],

        'pdf' => [
            'driver' => 'daily',
            'path' => storage_path('logs/pdf.log'),
            'level' => env('PDF_LOG_LEVEL', 'notice'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'cache' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cache.log'),
            'level' => env('CACHE_LOG_LEVEL', 'warning'),
            'days' => 7,
            'replace_placeholders' => true,
        ],

    ],

];
