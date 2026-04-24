<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes User — zone connectée
|--------------------------------------------------------------------------
|
| Surfaces de la zone connectée Floty : dashboard, flotte, planning,
| déclarations, etc. Toutes les routes de ce groupe sont préfixées
| `/app` et protégées par `auth` au niveau du groupe (pas par route,
| pour garantir qu'aucun oubli de middleware ne laisse passer une
| page sensible).
|
| Les noms de routes sont préfixés `user.` pour faciliter le filtrage
| (`php artisan route:list --name=user`).
|
| Les controllers vivront dans `app/Http/Controllers/User/{Domaine}/`.
|
*/

Route::middleware('auth')
    ->prefix('app')
    ->name('user.')
    ->group(function (): void {
        // Phase 03 — ajouter la redirection après login :
        // Route::get('/dashboard', [DashboardController::class, '__invoke'])->name('dashboard');
        //
        // Phase 04-11 — resources CRUD par domaine :
        // Route::resource('vehicles', VehicleController::class);
        // Route::resource('companies', CompanyController::class);
        // Route::resource('drivers', DriverController::class);
        // Route::resource('assignments', AssignmentController::class);
        // Route::resource('declarations', DeclarationController::class)->only(['index', 'show', 'update']);
    });
