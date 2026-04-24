<?php

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
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
