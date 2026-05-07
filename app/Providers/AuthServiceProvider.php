<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\BillingSettings;
use App\Models\Invoice;
use App\Models\VehicleYearlyPricing;
use App\Policies\BillingSettingsPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\VehicleYearlyPricingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Enregistre les Policies du domaine Phase 14 (facturation).
 *
 * Les Policies sont des stubs V1 (toutes leurs méthodes retournent
 * `true`) destinées à recevoir la logique multi-tenant V2 sans
 * refactoring des controllers, qui appellent déjà `Gate::authorize`.
 *
 * Les autres domaines (Vehicle, Company, Driver, Contract) recevront
 * leurs Policies progressivement quand on les touchera, sans urgence
 * en V1 mono-tenant.
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Invoice::class => InvoicePolicy::class,
        BillingSettings::class => BillingSettingsPolicy::class,
        VehicleYearlyPricing::class => VehicleYearlyPricingPolicy::class,
    ];
}
