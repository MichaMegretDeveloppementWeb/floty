<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Auth
|--------------------------------------------------------------------------
|
| V1 MVP : login + logout uniquement.
| Les flux forgot-password / reset-password / change-password sont reportés
| post-MVP (cadrage ADR-0012).
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');

    // Double couche anti brute-force :
    //   - Middleware throttle:10,2 = 10 req/2min/IP avant d'atteindre l'app
    //     (barrière externe stricte, coupe la requête au niveau routing)
    //   - Service LoginAttemptService = 5/2min couple (email,IP) + 50/2min IP
    //     (couche applicative, audit log Lockout + messages FR ; en trafic
    //     normal le cap IP=50 n'est jamais atteint puisque le middleware
    //     externe coupe à 10. Conservé comme défense en profondeur.)
    // Cf. ADR-0011 § 3 + plan-remédiation Vague 1 Lot 1 D4.
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,2')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
