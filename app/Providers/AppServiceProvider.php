<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Contracts\Pdf\PlanningPdfRendererInterface;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Pdf\BladeDomPdfDeclarationRenderer;
use App\Services\Pdf\BladeDomPdfPlanningRenderer;
use App\Services\Pdf\DeclarationPdfStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     *
     * Services exposing per-request in-memory caches are bound as
     * singletons so all consumers within an HTTP request share state.
     */
    public function register(): void
    {
        $this->app->singleton(AvailableYearsResolver::class);
        $this->app->singleton(BillingBreakdownService::class);

        $this->app->bind(DeclarationPdfRendererInterface::class, BladeDomPdfDeclarationRenderer::class);
        $this->app->bind(PlanningPdfRendererInterface::class, BladeDomPdfPlanningRenderer::class);

        $this->app->bind(
            DeclarationPdfStorage::class,
            fn (): DeclarationPdfStorage => new DeclarationPdfStorage(
                config('floty.declarations.pdf_storage_disk') ?? 'local',
            ),
        );
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        $this->configureDates();
        $this->configureEloquent();
        $this->configureDatabase();
        $this->configurePasswordDefaults();
        $this->configureUrlScheme();
    }

    /**
     * Use CarbonImmutable globally and align Carbon's locale to the app locale.
     */
    protected function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
        Carbon::setLocale(config('app.locale', 'fr'));
    }

    /**
     * Enable Eloquent strict mode outside of production to catch lazy
     * loading, missing attributes and silently discarded assignments early.
     */
    protected function configureEloquent(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Prevent destructive database commands (migrate:fresh, db:wipe…) in production.
     */
    protected function configureDatabase(): void
    {
        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );
    }

    /**
     * Apply the Floty password policy in production; relax defaults elsewhere
     * so factories and seeders can generate short passwords.
     */
    protected function configurePasswordDefaults(): void
    {
        Password::defaults(
            fn (): ?Password => $this->app->isProduction()
                ? Password::min(8)->uncompromised()
                : null,
        );
    }

    /**
     * Force HTTPS on generated URLs in production (ADR-0011 § 1).
     */
    protected function configureUrlScheme(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
