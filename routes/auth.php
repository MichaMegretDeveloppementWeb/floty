<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ChangePasswordController;
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

    // Throttle 3/5min sur la soumission · anti-spam mail (un envoi =
    // coût SMTP réel). Le throttle interne `Password::sendResetLink()`
    // (60s entre demandes pour un même email, cf. config/auth.php
    // passwords.users.throttle) cape déjà la fréquence côté broker.
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:3,5')
        ->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])
        ->name('password.reset');

    // Throttle 5/10min sur la soumission · marge raisonnable si
    // l'utilisateur se trompe sur la confirmation. Le token est hashé
    // et single-use, le risque bruteforce est faible.
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/profile/change-password', [ChangePasswordController::class, 'show'])
        ->name('profile.change-password.show');

    // Throttle 5/10min · le user est déjà connecté donc le risque
    // bruteforce est limité ; on cap pour éviter qu'un script abuse
    // d'une session volée tout en laissant la marge à un user qui se
    // trompe plusieurs fois sur le current password ou la confirmation.
    Route::post('/profile/change-password', [ChangePasswordController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('profile.change-password.update');
});
