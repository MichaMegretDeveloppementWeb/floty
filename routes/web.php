<?php

use App\Http\Controllers\Web\Dev\UiKitShowcaseController;
use App\Http\Controllers\Web\Dev\UiKitUserLayoutController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Routes de développement (local uniquement)
|--------------------------------------------------------------------------
|
| Ces routes ne sont exposées qu'en environnement local. Elles servent à la
| mise au point du design system et ne doivent jamais être accessibles en
| production.
*/

if (App::environment('local')) {
    Route::prefix('dev/ui-kit')->name('dev.ui-kit.')->group(function (): void {
        Route::get('/', UiKitShowcaseController::class)->name('index');
        Route::get('/layout-user', UiKitUserLayoutController::class)
            ->name('layout-user');
    });
}
