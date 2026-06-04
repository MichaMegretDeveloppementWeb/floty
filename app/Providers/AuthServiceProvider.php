<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\BillingSettings;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\Driver;
use App\Models\FiscalDeclaration;
use App\Models\FiscalRiskSettings;
use App\Models\Invoice;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Models\VehicleYearlyPricing;
use App\Policies\BillingSettingsPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContractDocumentPolicy;
use App\Policies\ContractPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\DriverPolicy;
use App\Policies\FiscalDeclarationPolicy;
use App\Policies\FiscalRiskSettingsPolicy;
use App\Policies\FiscalRulePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PlanningPolicy;
use App\Policies\VehicleEventPolicy;
use App\Policies\VehicleFiscalCharacteristicsPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleYearlyPricingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Registers all domain Policies and standalone Gate abilities.
 *
 * V1 policies are stubs returning `true`; they exist so controllers can
 * wire `Gate::authorize(...)` calls today and receive multi-tenant
 * scoping in V2 without further refactoring (ADR-0011 § 7).
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Model → Policy mapping.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Invoice::class => InvoicePolicy::class,
        BillingSettings::class => BillingSettingsPolicy::class,
        FiscalRiskSettings::class => FiscalRiskSettingsPolicy::class,
        FiscalDeclaration::class => FiscalDeclarationPolicy::class,
        VehicleYearlyPricing::class => VehicleYearlyPricingPolicy::class,
        Contract::class => ContractPolicy::class,
        ContractDocument::class => ContractDocumentPolicy::class,
        Company::class => CompanyPolicy::class,
        Driver::class => DriverPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        VehicleFiscalCharacteristics::class => VehicleFiscalCharacteristicsPolicy::class,
        VehicleEvent::class => VehicleEventPolicy::class,
    ];

    /**
     * Define standalone Gate abilities for domains without a backing Model
     * (Planning, Dashboard, FiscalRule).
     */
    public function boot(): void
    {
        Gate::define('view-planning', [PlanningPolicy::class, 'viewAny']);
        Gate::define('view-dashboard', [DashboardPolicy::class, 'viewAny']);
        Gate::define('view-fiscal-rules', [FiscalRulePolicy::class, 'viewAny']);
    }
}
