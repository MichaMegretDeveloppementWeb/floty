<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Dev\UiKitShowcaseController;
use App\Http\Controllers\Web\Dev\UiKitUserLayoutController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Development Routes (local only)
|--------------------------------------------------------------------------
|
| Exposed only in the local environment. Used to develop the design system
| and must never be reachable in production.
*/

if (App::environment('local')) {
    Route::prefix('dev/ui-kit')->name('dev.ui-kit.')->group(function (): void {
        Route::get('/', UiKitShowcaseController::class)->name('index');
        Route::get('/layout-user', UiKitUserLayoutController::class)
            ->name('layout-user');
    });
}
