<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Login, logout, password reset and authenticated password change.
| See ADR-0012.
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');

    // Two-layer brute-force protection (ADR-0011 § 3):
    //   - Middleware throttle:10,2 = 10 req / 2 min / IP at the routing layer.
    //   - LoginAttemptService = 5 / 2 min per (email, IP) + 10 / 2 min per IP,
    //     with audit log and FR messages, as defense in depth.
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,2')
        ->name('login.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])
        ->name('password.request');

    // Anti-spam on reset link emails. The broker's own per-email throttle
    // (config/auth.php passwords.users.throttle) caps the rate further.
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:3,5')
        ->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])
        ->name('password.reset');

    // Reset tokens are hashed and single-use; cap submissions to absorb
    // a few user mistakes on the confirmation field.
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/profile/change-password', [ChangePasswordController::class, 'show'])
        ->name('profile.change-password.show');

    // User is already authenticated, so brute-force risk is low; cap to
    // contain abuse of a stolen session while allowing normal retries.
    Route::post('/profile/change-password', [ChangePasswordController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('profile.change-password.update');
});
