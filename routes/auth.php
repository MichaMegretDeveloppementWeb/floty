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

    // Double couche anti brute-force (ADR-0011 § 3 rev. 1.1) :
    //   - Middleware throttle:10,2 = 10 req/2min/IP avant d'atteindre l'app
    //     (barrière externe stricte, coupe la requête au niveau routing)
    //   - Service LoginAttemptService = 5/2min couple (email,IP) + 10/2min IP
    //     (couche applicative, audit log Lockout + messages FR · alignée sur
    //     le throttle externe pour cohérence ; sert de défense en profondeur
    //     si le middleware est bypassé, par exemple via header proxy mal posé).
    // Cf. plan-remédiation Vague 1 Lot 1 D1 (F-10-001) + D4 (F-30-002).
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,2')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
