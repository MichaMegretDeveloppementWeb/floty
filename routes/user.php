<?php

declare(strict_types=1);

use App\Http\Controllers\User\Assignment\AssignmentController;
use App\Http\Controllers\User\Company\CompanyController;
use App\Http\Controllers\User\Dashboard\DashboardController;
use App\Http\Controllers\User\FiscalRule\FiscalRuleController;
use App\Http\Controllers\User\Planning\PlanningController;
use App\Http\Controllers\User\Vehicle\VehicleController;
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

        // Fiscal rules — consultation only
        Route::get('/fiscal-rules', [FiscalRuleController::class, 'index'])->name('fiscal-rules.index');
    });
