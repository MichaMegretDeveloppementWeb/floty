<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\User\Billing\BillingSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Billing\BillingSettingsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlDefinitionWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionDocumentWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderLogReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderLogWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideWriteRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatReadRepositoryInterface;
use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatWriteRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureWriteRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentWriteRepositoryInterface;
use App\Repositories\User\Billing\BillingSettingsReadRepository;
use App\Repositories\User\Billing\BillingSettingsWriteRepository;
use App\Repositories\User\Company\CompanyReadRepository;
use App\Repositories\User\Company\CompanyWriteRepository;
use App\Repositories\User\Contract\ContractReadRepository;
use App\Repositories\User\Contract\ContractWriteRepository;
use App\Repositories\User\ContractDocument\ContractDocumentReadRepository;
use App\Repositories\User\ContractDocument\ContractDocumentWriteRepository;
use App\Repositories\User\Control\ControlDefinitionReadRepository;
use App\Repositories\User\Control\ControlDefinitionWriteRepository;
use App\Repositories\User\Control\ControlExecutionDocumentReadRepository;
use App\Repositories\User\Control\ControlExecutionDocumentWriteRepository;
use App\Repositories\User\Control\ControlExecutionReadRepository;
use App\Repositories\User\Control\ControlExecutionWriteRepository;
use App\Repositories\User\Control\ControlReminderLogReadRepository;
use App\Repositories\User\Control\ControlReminderLogWriteRepository;
use App\Repositories\User\Control\ControlReminderSettingsReadRepository;
use App\Repositories\User\Control\ControlReminderSettingsWriteRepository;
use App\Repositories\User\Control\VehicleControlOverrideReadRepository;
use App\Repositories\User\Control\VehicleControlOverrideWriteRepository;
use App\Repositories\User\Driver\DriverReadRepository;
use App\Repositories\User\Driver\DriverWriteRepository;
use App\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepository;
use App\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepository;
use App\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepository;
use App\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionWriteRepository;
use App\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsReadRepository;
use App\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsWriteRepository;
use App\Repositories\User\FiscalRule\FiscalRuleReadRepository;
use App\Repositories\User\Heartbeat\SchedulerHeartbeatReadRepository;
use App\Repositories\User\Heartbeat\SchedulerHeartbeatWriteRepository;
use App\Repositories\User\Invoice\InvoiceReadRepository;
use App\Repositories\User\Invoice\InvoiceWriteRepository;
use App\Repositories\User\RentalDiscount\RentalDiscountReadRepository;
use App\Repositories\User\RentalDiscount\RentalDiscountWriteRepository;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepository;
use App\Repositories\User\Vehicle\VehicleReadRepository;
use App\Repositories\User\Vehicle\VehicleWriteRepository;
use App\Repositories\User\Vehicle\VehicleYearlyPricingReadRepository;
use App\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepository;
use App\Repositories\User\VehicleEvent\VehicleEventNatureReadRepository;
use App\Repositories\User\VehicleEvent\VehicleEventNatureWriteRepository;
use App\Repositories\User\VehicleEvent\VehicleEventReadRepository;
use App\Repositories\User\VehicleEvent\VehicleEventWriteRepository;
use App\Repositories\User\VehicleEventDocument\VehicleEventDocumentReadRepository;
use App\Repositories\User\VehicleEventDocument\VehicleEventDocumentWriteRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds Repository contracts to their Eloquent implementations.
 *
 * Repositories are stateless query objects and are registered as
 * singletons so a single instance is reused per HTTP request, avoiding
 * repeated instantiation when several services inject the same contract.
 */
final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Contract → Implementation bindings, exposed as singletons.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        // Vehicle
        VehicleReadRepositoryInterface::class => VehicleReadRepository::class,
        VehicleWriteRepositoryInterface::class => VehicleWriteRepository::class,
        VehicleFiscalCharacteristicsReadRepositoryInterface::class => VehicleFiscalCharacteristicsReadRepository::class,
        VehicleFiscalCharacteristicsWriteRepositoryInterface::class => VehicleFiscalCharacteristicsWriteRepository::class,
        VehicleYearlyPricingReadRepositoryInterface::class => VehicleYearlyPricingReadRepository::class,
        VehicleYearlyPricingWriteRepositoryInterface::class => VehicleYearlyPricingWriteRepository::class,

        // Company
        CompanyReadRepositoryInterface::class => CompanyReadRepository::class,
        CompanyWriteRepositoryInterface::class => CompanyWriteRepository::class,

        // Contract
        ContractReadRepositoryInterface::class => ContractReadRepository::class,
        ContractWriteRepositoryInterface::class => ContractWriteRepository::class,
        ContractDocumentReadRepositoryInterface::class => ContractDocumentReadRepository::class,
        ContractDocumentWriteRepositoryInterface::class => ContractDocumentWriteRepository::class,

        // Driver
        DriverReadRepositoryInterface::class => DriverReadRepository::class,
        DriverWriteRepositoryInterface::class => DriverWriteRepository::class,

        // Fiscal rules
        FiscalRuleReadRepositoryInterface::class => FiscalRuleReadRepository::class,

        // Invoicing
        InvoiceReadRepositoryInterface::class => InvoiceReadRepository::class,
        InvoiceWriteRepositoryInterface::class => InvoiceWriteRepository::class,
        RentalDiscountReadRepositoryInterface::class => RentalDiscountReadRepository::class,
        RentalDiscountWriteRepositoryInterface::class => RentalDiscountWriteRepository::class,
        BillingSettingsReadRepositoryInterface::class => BillingSettingsReadRepository::class,
        BillingSettingsWriteRepositoryInterface::class => BillingSettingsWriteRepository::class,

        // Fiscal declaration workflow (ADR-0015)
        FiscalRiskSettingsReadRepositoryInterface::class => FiscalRiskSettingsReadRepository::class,
        FiscalRiskSettingsWriteRepositoryInterface::class => FiscalRiskSettingsWriteRepository::class,
        FiscalDeclarationReadRepositoryInterface::class => FiscalDeclarationReadRepository::class,
        FiscalDeclarationWriteRepositoryInterface::class => FiscalDeclarationWriteRepository::class,
        FiscalReviewDecisionReadRepositoryInterface::class => FiscalReviewDecisionReadRepository::class,
        FiscalReviewDecisionWriteRepositoryInterface::class => FiscalReviewDecisionWriteRepository::class,

        // Controls (Chantier B)
        ControlReminderSettingsReadRepositoryInterface::class => ControlReminderSettingsReadRepository::class,
        ControlReminderSettingsWriteRepositoryInterface::class => ControlReminderSettingsWriteRepository::class,
        ControlDefinitionReadRepositoryInterface::class => ControlDefinitionReadRepository::class,
        ControlDefinitionWriteRepositoryInterface::class => ControlDefinitionWriteRepository::class,

        // Controls par-véhicule (Chantier B / B2)
        VehicleControlOverrideReadRepositoryInterface::class => VehicleControlOverrideReadRepository::class,
        VehicleControlOverrideWriteRepositoryInterface::class => VehicleControlOverrideWriteRepository::class,
        ControlExecutionReadRepositoryInterface::class => ControlExecutionReadRepository::class,
        ControlExecutionWriteRepositoryInterface::class => ControlExecutionWriteRepository::class,
        ControlExecutionDocumentReadRepositoryInterface::class => ControlExecutionDocumentReadRepository::class,
        ControlExecutionDocumentWriteRepositoryInterface::class => ControlExecutionDocumentWriteRepository::class,

        // Controls reminders (Chantier B / B3)
        ControlReminderLogReadRepositoryInterface::class => ControlReminderLogReadRepository::class,
        ControlReminderLogWriteRepositoryInterface::class => ControlReminderLogWriteRepository::class,
        SchedulerHeartbeatReadRepositoryInterface::class => SchedulerHeartbeatReadRepository::class,
        SchedulerHeartbeatWriteRepositoryInterface::class => SchedulerHeartbeatWriteRepository::class,

        // VehicleEvent
        VehicleEventNatureReadRepositoryInterface::class => VehicleEventNatureReadRepository::class,
        VehicleEventNatureWriteRepositoryInterface::class => VehicleEventNatureWriteRepository::class,
        VehicleEventReadRepositoryInterface::class => VehicleEventReadRepository::class,
        VehicleEventWriteRepositoryInterface::class => VehicleEventWriteRepository::class,
        VehicleEventDocumentReadRepositoryInterface::class => VehicleEventDocumentReadRepository::class,
        VehicleEventDocumentWriteRepositoryInterface::class => VehicleEventDocumentWriteRepository::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return array_keys($this->singletons);
    }
}
