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
use App\Models\Unavailability;
use App\Models\Vehicle;
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
use App\Policies\UnavailabilityPolicy;
use App\Policies\VehicleFiscalCharacteristicsPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleYearlyPricingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Enregistre toutes les Policies du domaine applicatif.
 *
 * Les Policies sont des stubs V1 (toutes leurs méthodes retournent
 * `true`) destinées à recevoir la logique multi-tenant V2 sans
 * refactoring des controllers, qui appellent déjà `Gate::authorize`.
 *
 * Migration V2 attendue · scoper l'accès par appartenance à la société
 * de location (qui édite les contrats/factures) ou par entreprise
 * utilisatrice destinataire selon le rôle de l'utilisateur connecté.
 *
 * Cf. ADR-0011 § 7 + plan-remédiation Vague 1 Lot 1 D6/D7 (F-12-001 + F-30-003).
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapping Model → Policy.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Phase 14 (facturation)
        Invoice::class => InvoicePolicy::class,
        BillingSettings::class => BillingSettingsPolicy::class,
        FiscalRiskSettings::class => FiscalRiskSettingsPolicy::class,
        FiscalDeclaration::class => FiscalDeclarationPolicy::class,
        VehicleYearlyPricing::class => VehicleYearlyPricingPolicy::class,

        // Vague 1 sécurité (Lot 1 D6 + D7)
        Contract::class => ContractPolicy::class,
        ContractDocument::class => ContractDocumentPolicy::class,
        Company::class => CompanyPolicy::class,
        Driver::class => DriverPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        VehicleFiscalCharacteristics::class => VehicleFiscalCharacteristicsPolicy::class,
        Unavailability::class => UnavailabilityPolicy::class,
    ];

    public function boot(): void
    {
        // Abilities standalone · Planning, Dashboard et FiscalRule n'ont
        // pas de Model dédié · on les définit comme intentions nommées
        // que les controllers appellent via `Gate::authorize('view-...')`.
        // Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
        Gate::define('view-planning', [PlanningPolicy::class, 'viewAny']);
        Gate::define('view-dashboard', [DashboardPolicy::class, 'viewAny']);
        Gate::define('view-fiscal-rules', [FiscalRulePolicy::class, 'viewAny']);
    }
}
