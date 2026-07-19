<?php

use App\Http\Controllers\Api\V1\AgenciesController;
use App\Http\Controllers\Api\V1\AgencyCatalogController;
use App\Http\Controllers\Api\V1\CatalogMetadataController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    Route::middleware(['auth:sanctum', 'api.token-owner-active', 'throttle:api'])->group(function (): void {
        Route::middleware('abilities:agencies:read')->group(function (): void {
            Route::get('/agencies', [AgencyCatalogController::class, 'index'])->name('agencies.index');
            Route::get('/agencies/search', [AgenciesController::class, 'search'])->name('agencies.search');
            Route::get('/agencies/version', [AgenciesController::class, 'version'])->name('agencies.version');
            Route::get('/agencies/snapshot', [AgenciesController::class, 'snapshot'])->name('agencies.snapshot');
            Route::get('/catalog/metadata', CatalogMetadataController::class)->name('catalog.metadata');
            Route::get('/agencies/{code}', [AgencyCatalogController::class, 'show'])->name('agencies.show');
        });

        Route::get('/me', MeController::class)->middleware('abilities:profile:read')->name('me');
    });
});
