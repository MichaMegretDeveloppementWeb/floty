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
use App\Http\Controllers\User\Settings\BillingSettingsController;
use App\Http\Controllers\User\Settings\FiscalRiskSettingsController;
use App\Http\Controllers\User\Unavailability\UnavailabilityController;
use App\Http\Controllers\User\Unavailability\UnavailabilityDocumentController;
use App\Http\Controllers\User\Vehicle\VehicleController;
use App\Http\Controllers\User\Vehicle\VehicleFiscalCharacteristicsController;
use App\Http\Controllers\User\Vehicle\VehicleYearlyPricingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes User · zone connectée
|--------------------------------------------------------------------------
|
| Préfixe URL `/app`, middleware `auth` au niveau du groupe, noms de
| routes préfixés `user.`.
*/

Route::middleware('auth')
    ->prefix('app')
    ->name('user.')
    ->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Companies
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('/companies', [CompanyController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('companies.store');
        Route::get('/companies/{company}', [CompanyController::class, 'show'])
            ->whereNumber('company')
            ->name('companies.show');
        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])
            ->whereNumber('company')
            ->name('companies.edit');
        Route::patch('/companies/{company}', [CompanyController::class, 'update'])
            ->whereNumber('company')
            ->middleware('throttle:60,1')
            ->name('companies.update');

        // Vehicles
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/vehicles', [VehicleController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('vehicles.store');
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])
            ->whereNumber('vehicle')
            ->name('vehicles.show');
        // Endpoints lazy JSON pour la fiche véhicule (chantier η Phase 2
        // refonte onglets) · sélecteurs locaux dans cartes
        // Utilisation/Répartition + Fiscalité, fetchent à la demande
        // avec cache client (composable `useYearLazy`).
        Route::get('/vehicles/{vehicle}/usage-stats', [VehicleController::class, 'usageStats'])
            ->whereNumber('vehicle')
            ->name('vehicles.usage-stats');
        Route::get('/vehicles/{vehicle}/full-year-breakdown', [VehicleController::class, 'fullYearBreakdown'])
            ->whereNumber('vehicle')
            ->name('vehicles.full-year-breakdown');
        Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])
            ->whereNumber('vehicle')
            ->name('vehicles.edit');
        Route::patch('/vehicles/{vehicle}', [VehicleController::class, 'update'])
            ->whereNumber('vehicle')
            ->middleware('throttle:60,1')
            ->name('vehicles.update');
        Route::post('/vehicles/{vehicle}/exit', [VehicleController::class, 'exit'])
            ->whereNumber('vehicle')
            ->middleware('throttle:60,1')
            ->name('vehicles.exit');
        Route::post('/vehicles/{vehicle}/reactivate', [VehicleController::class, 'reactivate'])
            ->whereNumber('vehicle')
            ->middleware('throttle:60,1')
            ->name('vehicles.reactivate');

        // Vehicle fiscal characteristics · CRUD complet depuis la modale
        // Historique de la page Show véhicule (création + édition + suppression).
        Route::post(
            '/vehicles/{vehicle}/fiscal-characteristics',
            [VehicleFiscalCharacteristicsController::class, 'store'],
        )
            ->whereNumber('vehicle')
            ->middleware('throttle:60,1')
            ->name('vehicle-fiscal-characteristics.store');
        Route::patch(
            '/vehicle-fiscal-characteristics/{vehicleFiscalCharacteristic}',
            [VehicleFiscalCharacteristicsController::class, 'update'],
        )
            ->whereNumber('vehicleFiscalCharacteristic')
            ->middleware('throttle:60,1')
            ->name('vehicle-fiscal-characteristics.update');
        Route::delete(
            '/vehicle-fiscal-characteristics/{vehicleFiscalCharacteristic}',
            [VehicleFiscalCharacteristicsController::class, 'destroy'],
        )
            ->whereNumber('vehicleFiscalCharacteristic')
            ->middleware('throttle:60,1')
            ->name('vehicle-fiscal-characteristics.destroy');

        // Vehicle yearly pricings (Phase 14 facturation V1.2) · tarifs
        // jour/semaine/mois × véhicule × année. Upsert idempotent garanti
        // par UNIQUE(vehicle_id, year). Endpoints opérés depuis la page
        // Show véhicule (chantier 14.B intégrera un PricingEditor inline).
        Route::post(
            '/vehicles/{vehicle}/yearly-pricings',
            [VehicleYearlyPricingController::class, 'store'],
        )
            ->whereNumber('vehicle')
            ->middleware('throttle:60,1')
            ->name('vehicle-yearly-pricings.store');
        Route::delete(
            '/vehicles/{vehicle}/yearly-pricings/{year}',
            [VehicleYearlyPricingController::class, 'destroy'],
        )
            ->whereNumber('vehicle')
            ->whereNumber('year')
            ->middleware('throttle:60,1')
            ->name('vehicle-yearly-pricings.destroy');

        // Settings > Facturation (Phase 14.G V1.2) · émetteur de facture
        Route::get('/settings/billing', [BillingSettingsController::class, 'edit'])
            ->name('settings.billing.edit');
        Route::post('/settings/billing', [BillingSettingsController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('settings.billing.update');

        // Settings > Détection de risque (Phase 11 D1, ADR-0015 § D7) ·
        // seuils paramétrables de la grille de classification fiscale.
        Route::get('/settings/fiscal-risk', [FiscalRiskSettingsController::class, 'edit'])
            ->name('settings.fiscal-risk.edit');
        Route::post('/settings/fiscal-risk', [FiscalRiskSettingsController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('settings.fiscal-risk.update');

        // Invoices · Phase 14 V1.2 (Index + Show + génération + téléchargement)
        // Throttle 6/min sur les mutations PDF-générantes (generate /
        // regenerate) : chaque appel render dompdf + write fs (~0.5-2 s).
        // 6/min = 1 toutes les 10 s, largement suffisant en usage normal,
        // garde-fou contre la saturation serveur.
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])
            ->middleware('throttle:6,1')
            ->name('invoices.generate');
        // `withTrashed` (D5.10.P) : les versions obsolètes (soft-deletées
        // par régénération) restent navigables via leur fiche Show et
        // leur PDF reste téléchargeable · audit trail légal.
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
            ->whereNumber('invoice')
            ->withTrashed()
            ->name('invoices.show');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])
            ->whereNumber('invoice')
            ->withTrashed()
            ->name('invoices.download');
        // Annulation (Phase 14.I) · seule mutation autorisée par la
        // doctrine immuabilité ; permet de regénérer après modif du
        // périmètre (contrat ajouté/modifié sur un mois déjà facturé).
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])
            ->whereNumber('invoice')
            ->middleware('throttle:6,1')
            ->name('invoices.destroy');
        // Régénération (Phase 14.I+) · annule + recrée en une transaction.
        Route::post('/invoices/{invoice}/regenerate', [InvoiceController::class, 'regenerate'])
            ->whereNumber('invoice')
            ->middleware('throttle:6,1')
            ->name('invoices.regenerate');

        // Unavailabilities · CRUD opéré depuis la page Show véhicule
        Route::post('/unavailabilities', [UnavailabilityController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('unavailabilities.store');
        Route::patch('/unavailabilities/{unavailability}', [UnavailabilityController::class, 'update'])
            ->whereNumber('unavailability')
            ->middleware('throttle:60,1')
            ->name('unavailabilities.update');
        Route::delete('/unavailabilities/{unavailability}', [UnavailabilityController::class, 'destroy'])
            ->whereNumber('unavailability')
            ->middleware('throttle:60,1')
            ->name('unavailabilities.destroy');

        // Unavailability documents (P1) · justificatifs image/PDF
        // (5 max, 5 Mo). Aligné sur le pattern contract documents.
        Route::post('/unavailabilities/{unavailability}/documents', [UnavailabilityDocumentController::class, 'store'])
            ->whereNumber('unavailability')
            ->middleware('throttle:30,1')
            ->name('unavailabilities.documents.store');
        Route::get('/unavailabilities/{unavailability}/documents/{document}', [UnavailabilityDocumentController::class, 'show'])
            ->whereNumber(['unavailability', 'document'])
            ->name('unavailabilities.documents.show');
        Route::delete('/unavailabilities/{unavailability}/documents/{document}', [UnavailabilityDocumentController::class, 'destroy'])
            ->whereNumber(['unavailability', 'document'])
            ->middleware('throttle:60,1')
            ->name('unavailabilities.documents.destroy');

        // Planning global (heatmap annuelle) · vue d'ensemble maîtresse
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::get('/planning/week', [PlanningController::class, 'week'])
            ->middleware('throttle:120,1')
            ->name('planning.week');
        Route::post('/planning/preview-taxes', [PlanningController::class, 'previewTaxes'])
            ->middleware('throttle:30,1')
            ->name('planning.preview-taxes');
        Route::post('/planning/contracts', [PlanningController::class, 'storeBulk'])
            ->middleware('throttle:60,1')
            ->name('planning.contracts.store-bulk');

        // Planning Vue Entreprise (chantier P1+P2) : variante de la
        // heatmap focalisée sur une entreprise donnée.
        // Racine sans param : redirige vers la 1ʳᵉ entreprise existante
        // ou affiche la page Empty si aucune.
        Route::get('/planning/companies', [PlanningController::class, 'companyIndexRoot'])
            ->name('planning.companies.root');
        Route::get('/planning/companies/{company}', [PlanningController::class, 'companyIndex'])
            ->name('planning.companies.index');

        // Contracts (ADR-0014) · entité pivot du domaine fiscal.
        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
        Route::post('/contracts', [ContractController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('contracts.store');
        Route::post('/contracts/bulk', [ContractController::class, 'bulkStore'])
            ->middleware('throttle:30,1')
            ->name('contracts.bulk-store');
        Route::get('/contracts/{contract}', [ContractController::class, 'show'])
            ->whereNumber('contract')
            ->name('contracts.show');
        Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])
            ->whereNumber('contract')
            ->name('contracts.edit');
        Route::patch('/contracts/{contract}', [ContractController::class, 'update'])
            ->whereNumber('contract')
            ->middleware('throttle:60,1')
            ->name('contracts.update');
        Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])
            ->whereNumber('contract')
            ->middleware('throttle:60,1')
            ->name('contracts.destroy');

        // Contract documents (chantier 04.N) · PDF joints (5 max, 10 Mo)
        Route::post('/contracts/{contract}/documents', [ContractDocumentController::class, 'store'])
            ->whereNumber('contract')
            ->middleware('throttle:30,1')
            ->name('contracts.documents.store');
        Route::get('/contracts/{contract}/documents/{document}', [ContractDocumentController::class, 'show'])
            ->whereNumber(['contract', 'document'])
            ->name('contracts.documents.show');
        Route::delete('/contracts/{contract}/documents/{document}', [ContractDocumentController::class, 'destroy'])
            ->whereNumber(['contract', 'document'])
            ->middleware('throttle:60,1')
            ->name('contracts.documents.destroy');

        // Drivers (Phase 06 V1.2)
        Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
        Route::get('/drivers/options', [DriverController::class, 'contractOptions'])
            ->name('drivers.contract-options');
        Route::get('/drivers/create', [DriverController::class, 'create'])->name('drivers.create');
        Route::post('/drivers', [DriverController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('drivers.store');
        Route::get('/drivers/{driver}', [DriverController::class, 'show'])
            ->whereNumber('driver')
            ->name('drivers.show');
        Route::get('/drivers/{driver}/edit', [DriverController::class, 'edit'])
            ->whereNumber('driver')
            ->name('drivers.edit');
        Route::patch('/drivers/{driver}', [DriverController::class, 'update'])
            ->whereNumber('driver')
            ->middleware('throttle:60,1')
            ->name('drivers.update');
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])
            ->whereNumber('driver')
            ->middleware('throttle:60,1')
            ->name('drivers.destroy');

        // Drivers · memberships company (Phase 06 V1.2)
        // Lot 4 D13 (F-34-105) · extraits dans `DriverMembershipController` ·
        // les noms de routes restent identiques, le frontend (Wayfinder)
        // n'a aucun changement à faire.
        Route::post('/drivers/{driver}/memberships', [DriverMembershipController::class, 'attachCompany'])
            ->whereNumber('driver')
            ->middleware('throttle:60,1')
            ->name('drivers.memberships.store');
        Route::get('/drivers/{driver}/memberships/{companyId}/future-contracts', [DriverMembershipController::class, 'futureContractsForLeave'])
            ->whereNumber(['driver', 'companyId'])
            ->name('drivers.memberships.future-contracts');
        Route::patch('/drivers/{driver}/memberships/{companyId}/leave', [DriverMembershipController::class, 'leaveCompany'])
            ->whereNumber(['driver', 'companyId'])
            ->middleware('throttle:60,1')
            ->name('drivers.memberships.leave');
        Route::patch('/drivers/{driver}/memberships/{pivotId}', [DriverMembershipController::class, 'updateMembership'])
            ->whereNumber(['driver', 'pivotId'])
            ->middleware('throttle:60,1')
            ->name('drivers.memberships.update');
        Route::delete('/drivers/{driver}/memberships/{pivotId}', [DriverMembershipController::class, 'detachCompany'])
            ->whereNumber(['driver', 'pivotId'])
            ->middleware('throttle:60,1')
            ->name('drivers.memberships.destroy');

        // Fiscal rules · consultation only
        Route::get('/fiscal-rules', [FiscalRuleController::class, 'index'])->name('fiscal-rules.index');

        // Déclarations fiscales (Phase 11 D4, ADR-0015 rev. 1.1).
        // Throttle 30/min sur les mutations cluster (storeDecision,
        // markDeferred). Throttle 6/min sur les mutations PDF-générantes
        // (generate, regenerate) : chaque appel render DomPDF + write fs.
        //
        // Lot 4 D13 (F-34-105) · controller éclaté en 3 par concern ·
        // `DeclarationController` (consultation) +
        // `DeclarationGenerationController` (cycle Draft→Generated) +
        // `DeclarationLifecycleController` (transitions terminales).
        // Les noms de routes restent identiques, le frontend n'a aucun
        // changement à faire (Wayfinder régénéré pointe sur le bon
        // controller automatiquement).
        Route::get('/declarations', [DeclarationController::class, 'index'])
            ->name('declarations.index');
        Route::post('/declarations/prepare', [DeclarationGenerationController::class, 'prepare'])
            ->middleware('throttle:30,1')
            ->name('declarations.prepare');
        Route::get('/declarations/{declaration}', [DeclarationController::class, 'show'])
            ->whereNumber('declaration')
            ->name('declarations.show');
        Route::get('/declarations/{declaration}/review', [DeclarationController::class, 'review'])
            ->whereNumber('declaration')
            ->name('declarations.review');
        Route::post('/declarations/{declaration}/decisions', [DeclarationGenerationController::class, 'storeDecision'])
            ->whereNumber('declaration')
            ->middleware('throttle:60,1')
            ->name('declarations.decisions.store');
        Route::post('/declarations/{declaration}/mark-deferred', [DeclarationLifecycleController::class, 'markDeferred'])
            ->whereNumber('declaration')
            ->middleware('throttle:30,1')
            ->name('declarations.mark-deferred');
        Route::post('/declarations/{declaration}/revert-defer', [DeclarationLifecycleController::class, 'revertDefer'])
            ->whereNumber('declaration')
            ->middleware('throttle:30,1')
            ->name('declarations.revert-defer');
        Route::post('/declarations/{declaration}/generate', [DeclarationGenerationController::class, 'generate'])
            ->whereNumber('declaration')
            ->middleware('throttle:6,1')
            ->name('declarations.generate');
        Route::post('/declarations/{declaration}/regenerate', [DeclarationGenerationController::class, 'regenerate'])
            ->whereNumber('declaration')
            ->middleware('throttle:6,1')
            ->name('declarations.regenerate');
        // Phase 13 D5.10.E · modification volontaire depuis Show (S5 → S7)
        // et suppression d'un brouillon (avec gestion intelligente du
        // predecessor selon la nature des motifs d'obsolescence).
        Route::post('/declarations/{declaration}/modify', [DeclarationGenerationController::class, 'modify'])
            ->whereNumber('declaration')
            ->middleware('throttle:6,1')
            ->name('declarations.modify');
        Route::delete('/declarations/{declaration}', [DeclarationLifecycleController::class, 'destroy'])
            ->whereNumber('declaration')
            ->middleware('throttle:30,1')
            ->name('declarations.destroy');
        Route::get('/declarations/{declaration}/download', [DeclarationController::class, 'download'])
            ->whereNumber('declaration')
            ->name('declarations.download');
    });
