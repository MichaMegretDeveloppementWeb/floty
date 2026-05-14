<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Auth
|--------------------------------------------------------------------------
|
| V1 · login + logout + forgot-password (reset-password + change-password
| livrés dans D4.3 + D4.4 du même plan-remédiation).
| Cf. ADR-0012 rev. 1.1.
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

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])
        ->name('password.request');

    // Throttle 3/15min anti spam reset (envoi d'emails). Plus strict que
    // le login car un envoi mail = coût SMTP réel.
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:3,15')
        ->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])
        ->name('password.reset');

    // Throttle 5/15min sur la soumission · plus permissif que `password.email`
    // (l'utilisateur peut faire 1 ou 2 erreurs de saisie sur le confirmation
    // password) mais reste strict pour bloquer le bruteforce du token.
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
