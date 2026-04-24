<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Auth
|--------------------------------------------------------------------------
|
| Surfaces d'authentification Floty : login, logout, forgot/reset password,
| changement forcé de mot de passe au premier login.
|
| Ces routes sont **publiques** (pas de middleware `auth`) à l'exception
| du logout et du changement forcé, qui requièrent une session. Les détails
| sont cadrés par ADR-0012 et implémentés en phase 03.
|
| Les controllers vivront dans `app/Http/Controllers/Auth/`.
|
*/

Route::middleware('guest')->group(function (): void {
    // Phase 03 — ajouter :
    // Route::get('/login', [LoginController::class, 'show'])->name('login');
    // Route::post('/login', [LoginController::class, 'store']);
    // Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    // Route::post('/forgot-password', [ForgotPasswordController::class, 'store']);
    // Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    // Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    // Phase 03 — ajouter :
    // Route::post('/logout', [LogoutController::class, '__invoke'])->name('logout');
    // Route::get('/profile/change-password', [ChangePasswordController::class, 'show'])->name('password.change');
    // Route::post('/profile/change-password', [ChangePasswordController::class, 'store']);
});
