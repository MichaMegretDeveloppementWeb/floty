<?php

declare(strict_types=1);

use App\Http\Controllers\User\Company\CompanyController;
use App\Http\Controllers\User\Contract\ContractController;
use App\Http\Controllers\User\Contract\ContractDocumentController;
use App\Http\Controllers\User\Dashboard\DashboardController;
use App\Http\Controllers\User\Driver\DriverController;
use App\Http\Controllers\User\Driver\DriverMembershipController;
use App\Http\Controllers\User\FiscalDeclaration\DeclarationController;
use App\Http\Controllers\User\FiscalDeclaration\DeclarationGenerationController;
use App\Http\Controllers\User\FiscalDeclaration\DeclarationLifecycleController;
use App\Http\Controllers\User\FiscalRule\FiscalRuleController;
use App\Http\Controllers\User\Invoice\InvoiceController;
use App\Http\Controllers\User\Planning\PlanningController;
use App\Http\Controllers\User\RentalDiscount\RentalDiscountController;
use App\Http\Controllers\User\Search\GlobalSearchController;
use App\Http\Controllers\User\Settings\BillingSettingsController;
use App\Http\Controllers\User\Settings\FiscalRiskSettingsController;
use App\Http\Controllers\User\Unavailability\UnavailabilityController;
use App\Http\Controllers\User\Unavailability\UnavailabilityDocumentController;
use App\Http\Controllers\User\Vehicle\VehicleController;
use App\Http\Controllers\User\Vehicle\VehicleFiscalCharacteristicsController;
use App\Http\Controllers\User\Vehicle\VehicleRegistryLookupController;
use App\Http\Controllers\User\Vehicle\VehicleYearlyPricingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Routes (authenticated area)
|--------------------------------------------------------------------------
|
| URL prefix `/app`, group middleware `auth`, route names prefixed `user.`.
*/

Route::middleware('auth')
    ->prefix('app')
    ->name('user.')
    ->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Global search · JSON endpoint consumed by the TopBar ⌘K palette.
        // No per-route throttle: the call is debounced client-side with a
        // 2-char minimum, and we want power users to be able to search as
        // intensively as they need without being blocked.
        Route::get('/search', GlobalSearchController::class)->name('search');

        // Companies
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('/companies', [CompanyController::class, 'store'])
            ->middleware('throttle:300,1')
            ->name('companies.store');
        Route::get('/companies/{company}', [CompanyController::class, 'show'])
            ->whereNumber('company')
            ->name('companies.show');
        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])
            ->whereNumber('company')
            ->name('companies.edit');
        Route::patch('/companies/{company}', [CompanyController::class, 'update'])
            ->whereNumber('company')
            ->middleware('throttle:300,1')
            ->name('companies.update');
        // Net monthly rental (post-discounts) for a company × year,
        // consumed by the Contract Create/Edit form (12-month mini timeline).
        // Read-only, called on every company/year change in the form.
        Route::get('/companies/{company}/monthly-rentals', [CompanyController::class, 'monthlyRentals'])
            ->whereNumber('company')
            ->name('companies.monthly-rentals');

        // Vehicles
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/vehicles', [VehicleController::class, 'store'])
            ->middleware('throttle:300,1')
            ->name('vehicles.store');
        Route::post('/vehicles/registry-lookup', VehicleRegistryLookupController::class)
            ->middleware('throttle:'.config('vehicle-registry.throttle', '20,1'))
            ->name('vehicles.registry-lookup');
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])
            ->whereNumber('vehicle')
            ->name('vehicles.show');
        // Lazy JSON endpoints for the vehicle detail page: per-year
        // selectors in the Usage/Distribution and Fiscal cards, fetched on
        // demand with client-side caching (composable `useYearLazy`).
        Route::get('/vehicles/{vehicle}/usage-stats', [VehicleController::class, 'usageStats'])
            ->whereNumber('vehicle')
            ->name('vehicles.usage-stats');
        Route::get('/vehicles/{vehicle}/full-year-breakdown', [VehicleController::class, 'fullYearBreakdown'])
            ->whereNumber('vehicle')
            ->name('vehicles.full-year-breakdown');
        // Calculation A · vehicle full-year tax, triggered when picking a
        // vehicle in the Contract Create/Edit form (`useVehicleFullYearTax`).
        // Read-only, no throttle so the form stays snappy when the user
        // explores several vehicles in a row.
        Route::get('/vehicles/{vehicle}/full-year-tax', [VehicleController::class, 'fullYearTax'])
            ->whereNumber('vehicle')
            ->name('vehicles.full-year-tax');
        // Daily/weekly/monthly rates for a given year, consumed by the
        // Contract Create/Edit form. Read-only.
        Route::get('/vehicles/{vehicle}/yearly-rates', [VehicleController::class, 'yearlyRates'])
            ->whereNumber('vehicle')
            ->name('vehicles.yearly-rates');
        Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])
            ->whereNumber('vehicle')
            ->name('vehicles.edit');
        Route::patch('/vehicles/{vehicle}', [VehicleController::class, 'update'])
            ->whereNumber('vehicle')
            ->middleware('throttle:300,1')
            ->name('vehicles.update');
        Route::post('/vehicles/{vehicle}/exit', [VehicleController::class, 'exit'])
            ->whereNumber('vehicle')
            ->middleware('throttle:300,1')
            ->name('vehicles.exit');
        Route::post('/vehicles/{vehicle}/reactivate', [VehicleController::class, 'reactivate'])
            ->whereNumber('vehicle')
            ->middleware('throttle:300,1')
            ->name('vehicles.reactivate');

        // Vehicle fiscal characteristics · full CRUD from the History
        // modal on the vehicle detail page.
        Route::post(
            '/vehicles/{vehicle}/fiscal-characteristics',
            [VehicleFiscalCharacteristicsController::class, 'store'],
        )
            ->whereNumber('vehicle')
            ->middleware('throttle:300,1')
            ->name('vehicle-fiscal-characteristics.store');
        Route::patch(
            '/vehicle-fiscal-characteristics/{vehicleFiscalCharacteristic}',
            [VehicleFiscalCharacteristicsController::class, 'update'],
        )
            ->whereNumber('vehicleFiscalCharacteristic')
            ->middleware('throttle:300,1')
            ->name('vehicle-fiscal-characteristics.update');
        Route::delete(
            '/vehicle-fiscal-characteristics/{vehicleFiscalCharacteristic}',
            [VehicleFiscalCharacteristicsController::class, 'destroy'],
        )
            ->whereNumber('vehicleFiscalCharacteristic')
            ->middleware('throttle:300,1')
            ->name('vehicle-fiscal-characteristics.destroy');

        // Vehicle yearly pricings · daily/weekly/monthly rates per vehicle
        // and year. Idempotent upsert via UNIQUE(vehicle_id, year).
        Route::post(
            '/vehicles/{vehicle}/yearly-pricings',
            [VehicleYearlyPricingController::class, 'store'],
        )
            ->whereNumber('vehicle')
            ->middleware('throttle:300,1')
            ->name('vehicle-yearly-pricings.store');
        Route::delete(
            '/vehicles/{vehicle}/yearly-pricings/{year}',
            [VehicleYearlyPricingController::class, 'destroy'],
        )
            ->whereNumber('vehicle')
            ->whereNumber('year')
            ->middleware('throttle:300,1')
            ->name('vehicle-yearly-pricings.destroy');

        // Settings > Billing · invoice issuer information.
        Route::get('/settings/billing', [BillingSettingsController::class, 'edit'])
            ->name('settings.billing.edit');
        Route::post('/settings/billing', [BillingSettingsController::class, 'update'])
            ->middleware('throttle:120,1')
            ->name('settings.billing.update');

        // Settings > Fiscal risk detection (ADR-0015 § D7) · configurable
        // thresholds for the fiscal classification grid.
        Route::get('/settings/fiscal-risk', [FiscalRiskSettingsController::class, 'edit'])
            ->name('settings.fiscal-risk.edit');
        Route::post('/settings/fiscal-risk', [FiscalRiskSettingsController::class, 'update'])
            ->middleware('throttle:120,1')
            ->name('settings.fiscal-risk.update');

        // Invoices
        //
        // PDF-generating mutations are throttled to 30/min · each call
        // renders dompdf + writes the file (~0.5-2 s). The cap is a
        // server-protection floor, well above any normal human pace.
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])
            ->middleware('throttle:30,1')
            ->name('invoices.generate');
        // Bulk generation · one call renders up to 12 PDFs sequentially.
        // Throttle 10/min (≤ 120 PDFs/min plafond) absorbs retries while
        // protecting the server from saturation.
        Route::post('/invoices/bulk-generate', [InvoiceController::class, 'bulkGenerate'])
            ->middleware('throttle:10,1')
            ->name('invoices.bulk-generate');
        // `withTrashed`: superseded versions (soft-deleted on regeneration)
        // stay navigable for legal audit trail.
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
            ->whereNumber('invoice')
            ->withTrashed()
            ->name('invoices.show');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])
            ->whereNumber('invoice')
            ->withTrashed()
            ->name('invoices.download');
        // Cancellation · only mutation allowed by the immutability doctrine;
        // enables regeneration after a perimeter change.
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])
            ->whereNumber('invoice')
            ->middleware('throttle:30,1')
            ->name('invoices.destroy');
        // Regeneration · cancels and recreates in a single transaction.
        Route::post('/invoices/{invoice}/regenerate', [InvoiceController::class, 'regenerate'])
            ->whereNumber('invoice')
            ->middleware('throttle:30,1')
            ->name('invoices.regenerate');

        // Unavailabilities · CRUD operated from the vehicle detail page.
        Route::post('/unavailabilities', [UnavailabilityController::class, 'store'])
            ->middleware('throttle:300,1')
            ->name('unavailabilities.store');
        Route::patch('/unavailabilities/{unavailability}', [UnavailabilityController::class, 'update'])
            ->whereNumber('unavailability')
            ->middleware('throttle:300,1')
            ->name('unavailabilities.update');
        Route::delete('/unavailabilities/{unavailability}', [UnavailabilityController::class, 'destroy'])
            ->whereNumber('unavailability')
            ->middleware('throttle:300,1')
            ->name('unavailabilities.destroy');

        // Unavailability documents · image/PDF justifications (5 max, 5 MB).
        // Mirrors the contract documents pattern.
        Route::post('/unavailabilities/{unavailability}/documents', [UnavailabilityDocumentController::class, 'store'])
            ->whereNumber('unavailability')
            ->middleware('throttle:120,1')
            ->name('unavailabilities.documents.store');
        Route::get('/unavailabilities/{unavailability}/documents/{document}', [UnavailabilityDocumentController::class, 'show'])
            ->whereNumber(['unavailability', 'document'])
            ->name('unavailabilities.documents.show');
        Route::delete('/unavailabilities/{unavailability}/documents/{document}', [UnavailabilityDocumentController::class, 'destroy'])
            ->whereNumber(['unavailability', 'document'])
            ->middleware('throttle:300,1')
            ->name('unavailabilities.documents.destroy');

        // Planning · global yearly heatmap view.
        // Drawer endpoints (week / previews) are read-only and called repeatedly
        // while the user explores date ranges. No per-route throttle: they rely
        // on the framework's default protections; rate-limiting them was
        // breaking the drawer workflow (30/min was hit by fine-grained tweaks).
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::get('/planning/week', [PlanningController::class, 'week'])->name('planning.week');
        Route::post('/planning/preview-taxes', [PlanningController::class, 'previewTaxes'])
            ->name('planning.preview-taxes');
        Route::post('/planning/preview-rentals', [PlanningController::class, 'previewRentals'])
            ->name('planning.preview-rentals');
        Route::post('/planning/contracts', [PlanningController::class, 'storeBulk'])
            ->middleware('throttle:300,1')
            ->name('planning.contracts.store-bulk');

        // Planning · company-scoped heatmap variant.
        // The root path (no param) redirects to the first existing company,
        // or renders an Empty page when none exists.
        Route::get('/planning/companies', [PlanningController::class, 'companyIndexRoot'])
            ->name('planning.companies.root');
        Route::get('/planning/companies/{company}', [PlanningController::class, 'companyIndex'])
            ->name('planning.companies.index');

        // Contracts (ADR-0014) · pivot entity of the fiscal domain.
        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
        Route::post('/contracts', [ContractController::class, 'store'])
            ->middleware('throttle:300,1')
            ->name('contracts.store');
        Route::post('/contracts/bulk', [ContractController::class, 'bulkStore'])
            ->middleware('throttle:120,1')
            ->name('contracts.bulk-store');
        Route::get('/contracts/{contract}', [ContractController::class, 'show'])
            ->whereNumber('contract')
            ->name('contracts.show');
        Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])
            ->whereNumber('contract')
            ->name('contracts.edit');
        Route::patch('/contracts/{contract}', [ContractController::class, 'update'])
            ->whereNumber('contract')
            ->middleware('throttle:300,1')
            ->name('contracts.update');
        Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])
            ->whereNumber('contract')
            ->middleware('throttle:300,1')
            ->name('contracts.destroy');

        // Contract documents · attached PDFs (5 max, 10 MB).
        Route::post('/contracts/{contract}/documents', [ContractDocumentController::class, 'store'])
            ->whereNumber('contract')
            ->middleware('throttle:120,1')
            ->name('contracts.documents.store');
        Route::get('/contracts/{contract}/documents/{document}', [ContractDocumentController::class, 'show'])
            ->whereNumber(['contract', 'document'])
            ->name('contracts.documents.show');
        Route::delete('/contracts/{contract}/documents/{document}', [ContractDocumentController::class, 'destroy'])
            ->whereNumber(['contract', 'document'])
            ->middleware('throttle:300,1')
            ->name('contracts.documents.destroy');

        // Drivers
        Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
        Route::get('/drivers/options', [DriverController::class, 'contractOptions'])
            ->name('drivers.contract-options');
        Route::get('/drivers/create', [DriverController::class, 'create'])->name('drivers.create');
        Route::post('/drivers', [DriverController::class, 'store'])
            ->middleware('throttle:300,1')
            ->name('drivers.store');
        Route::get('/drivers/{driver}', [DriverController::class, 'show'])
            ->whereNumber('driver')
            ->name('drivers.show');
        Route::get('/drivers/{driver}/edit', [DriverController::class, 'edit'])
            ->whereNumber('driver')
            ->name('drivers.edit');
        Route::patch('/drivers/{driver}', [DriverController::class, 'update'])
            ->whereNumber('driver')
            ->middleware('throttle:300,1')
            ->name('drivers.update');
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])
            ->whereNumber('driver')
            ->middleware('throttle:300,1')
            ->name('drivers.destroy');

        // Drivers · company memberships.
        Route::post('/drivers/{driver}/memberships', [DriverMembershipController::class, 'attachCompany'])
            ->whereNumber('driver')
            ->middleware('throttle:300,1')
            ->name('drivers.memberships.store');
        Route::get('/drivers/{driver}/memberships/{companyId}/future-contracts', [DriverMembershipController::class, 'futureContractsForLeave'])
            ->whereNumber(['driver', 'companyId'])
            ->name('drivers.memberships.future-contracts');
        Route::patch('/drivers/{driver}/memberships/{companyId}/leave', [DriverMembershipController::class, 'leaveCompany'])
            ->whereNumber(['driver', 'companyId'])
            ->middleware('throttle:300,1')
            ->name('drivers.memberships.leave');
        Route::patch('/drivers/{driver}/memberships/{pivotId}', [DriverMembershipController::class, 'updateMembership'])
            ->whereNumber(['driver', 'pivotId'])
            ->middleware('throttle:300,1')
            ->name('drivers.memberships.update');
        Route::delete('/drivers/{driver}/memberships/{pivotId}', [DriverMembershipController::class, 'detachCompany'])
            ->whereNumber(['driver', 'pivotId'])
            ->middleware('throttle:300,1')
            ->name('drivers.memberships.destroy');

        // Rental discounts · commercial discounts applied to a company's
        // monthly rental over a given period, optionally scoped to a subset
        // of vehicles. The `check-conflicts` endpoint feeds live feedback
        // in the Create/Edit form (debounced client-side).
        Route::get('/rental-discounts', [RentalDiscountController::class, 'index'])
            ->name('rental-discounts.index');
        Route::get('/rental-discounts/create', [RentalDiscountController::class, 'create'])
            ->name('rental-discounts.create');
        Route::post('/rental-discounts', [RentalDiscountController::class, 'store'])
            ->middleware('throttle:300,1')
            ->name('rental-discounts.store');
        // Read-only conflict probe called by the form on field changes
        // (debounced client-side). No throttle: blocking the form on a
        // live preview is hostile.
        Route::post('/rental-discounts/check-conflicts', [RentalDiscountController::class, 'checkConflicts'])
            ->name('rental-discounts.check-conflicts');
        Route::get('/rental-discounts/{rentalDiscount}', [RentalDiscountController::class, 'show'])
            ->whereNumber('rentalDiscount')
            ->name('rental-discounts.show');
        Route::get('/rental-discounts/{rentalDiscount}/edit', [RentalDiscountController::class, 'edit'])
            ->whereNumber('rentalDiscount')
            ->name('rental-discounts.edit');
        Route::patch('/rental-discounts/{rentalDiscount}', [RentalDiscountController::class, 'update'])
            ->whereNumber('rentalDiscount')
            ->middleware('throttle:300,1')
            ->name('rental-discounts.update');
        Route::delete('/rental-discounts/{rentalDiscount}', [RentalDiscountController::class, 'destroy'])
            ->whereNumber('rentalDiscount')
            ->middleware('throttle:300,1')
            ->name('rental-discounts.destroy');

        // Fiscal rules · read-only.
        Route::get('/fiscal-rules', [FiscalRuleController::class, 'index'])->name('fiscal-rules.index');

        // Fiscal declarations (ADR-0015).
        //
        // PDF-generating mutations (generate, regenerate, modify) are
        // throttled to 30/min · each call renders DomPDF and writes the file.
        // Other mutations sit at 120/min, well above any normal user pace
        // (anti-abuse cap, not a UX brake).
        //
        // The controller is split by concern:
        //   - DeclarationController            · read
        //   - DeclarationGenerationController  · Draft → Generated cycle
        //   - DeclarationLifecycleController   · terminal transitions
        Route::get('/declarations', [DeclarationController::class, 'index'])
            ->name('declarations.index');
        Route::post('/declarations/prepare', [DeclarationGenerationController::class, 'prepare'])
            ->middleware('throttle:120,1')
            ->name('declarations.prepare');
        // `show` is the single declaration screen (read view for Generated,
        // interactive review for the canonical Draft/Deferred head).
        Route::get('/declarations/{declaration}', [DeclarationController::class, 'show'])
            ->whereNumber('declaration')
            ->name('declarations.show');
        Route::post('/declarations/{declaration}/decisions', [DeclarationGenerationController::class, 'storeDecision'])
            ->whereNumber('declaration')
            ->middleware('throttle:300,1')
            ->name('declarations.decisions.store');
        Route::post('/declarations/{declaration}/mark-deferred', [DeclarationLifecycleController::class, 'markDeferred'])
            ->whereNumber('declaration')
            ->middleware('throttle:120,1')
            ->name('declarations.mark-deferred');
        Route::post('/declarations/{declaration}/revert-defer', [DeclarationLifecycleController::class, 'revertDefer'])
            ->whereNumber('declaration')
            ->middleware('throttle:120,1')
            ->name('declarations.revert-defer');
        Route::post('/declarations/{declaration}/generate', [DeclarationGenerationController::class, 'generate'])
            ->whereNumber('declaration')
            ->middleware('throttle:30,1')
            ->name('declarations.generate');
        Route::post('/declarations/{declaration}/regenerate', [DeclarationGenerationController::class, 'regenerate'])
            ->whereNumber('declaration')
            ->middleware('throttle:30,1')
            ->name('declarations.regenerate');
        // Voluntary modification from Show (S5 → S7) and draft deletion
        // (predecessor handling depends on obsolescence reasons).
        Route::post('/declarations/{declaration}/modify', [DeclarationGenerationController::class, 'modify'])
            ->whereNumber('declaration')
            ->middleware('throttle:30,1')
            ->name('declarations.modify');
        Route::delete('/declarations/{declaration}', [DeclarationLifecycleController::class, 'destroy'])
            ->whereNumber('declaration')
            ->middleware('throttle:120,1')
            ->name('declarations.destroy');
        Route::get('/declarations/{declaration}/download', [DeclarationController::class, 'download'])
            ->whereNumber('declaration')
            ->name('declarations.download');
    });
