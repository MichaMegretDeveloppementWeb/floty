<?php

declare(strict_types=1);

use App\Http\Controllers\User\Assignment\AssignmentController;
use App\Http\Controllers\User\Company\CompanyController;
use App\Http\Controllers\User\Contract\ContractController;
use App\Http\Controllers\User\Dashboard\DashboardController;
use App\Http\Controllers\User\FiscalRule\FiscalRuleController;
use App\Http\Controllers\User\Planning\PlanningController;
use App\Http\Controllers\User\Unavailability\UnavailabilityController;
use App\Http\Controllers\User\Vehicle\VehicleController;
use App\Http\Controllers\User\Vehicle\VehicleFiscalCharacteristicsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes User — zone connectée
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

        // Vehicles
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/vehicles', [VehicleController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('vehicles.store');
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])
            ->whereNumber('vehicle')
            ->name('vehicles.show');
        Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])
            ->whereNumber('vehicle')
            ->name('vehicles.edit');
        Route::patch('/vehicles/{vehicle}', [VehicleController::class, 'update'])
            ->whereNumber('vehicle')
            ->middleware('throttle:60,1')
            ->name('vehicles.update');

        // Vehicle fiscal characteristics — édition/suppression d'une
        // VFC depuis la modale Historique de la page Show véhicule.
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

        // Unavailabilities — CRUD opéré depuis la page Show véhicule
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

        // Assignments — « Attribution rapide » plein écran (sans POST dédié :
        // utilise l'endpoint /app/planning/assignments pour créer en masse)
        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/assignments/vehicle-dates', [AssignmentController::class, 'vehicleDates'])
            ->middleware('throttle:120,1')
            ->name('assignments.vehicle-dates');

        // Planning global (heatmap annuelle) — vue d'ensemble maîtresse
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::get('/planning/week', [PlanningController::class, 'week'])
            ->middleware('throttle:120,1')
            ->name('planning.week');
        Route::post('/planning/preview-taxes', [PlanningController::class, 'previewTaxes'])
            ->middleware('throttle:30,1')
            ->name('planning.preview-taxes');
        Route::post('/planning/assignments', [PlanningController::class, 'storeBulk'])
            ->middleware('throttle:60,1')
            ->name('planning.assignments.store-bulk');

        // Contracts (ADR-0014) — coexiste avec les routes assignments.*
        // pendant la transition (cleanup d'Assignment en chantier 04.H).
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

        // Fiscal rules — consultation only
        Route::get('/fiscal-rules', [FiscalRuleController::class, 'index'])->name('fiscal-rules.index');
    });
