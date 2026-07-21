<?php

use App\Http\Controllers\Api\V1\AgenciesController;
use App\Http\Controllers\Api\V1\AgencyCatalogController;
use App\Http\Controllers\Api\V1\AgencyChangesController;
use App\Http\Controllers\Api\V1\CatalogMetadataController;
use App\Http\Controllers\Api\V1\DniApiController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Modules\Ruc\Http\Controllers\RucApiController;
use App\Modules\Ruc\Http\Controllers\RucSearchApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');
    Route::middleware(['auth:sanctum', 'api.token-owner-active', 'api.private-cache'])->group(function (): void {
        Route::middleware(['throttle:api-agencias', 'api.audit:agencias', 'abilities:agencias:consultar'])->group(function (): void {
            Route::get('/agencias', [AgencyCatalogController::class, 'index'])->name('agencias.index');
            Route::get('/agencias/{id}', [AgencyCatalogController::class, 'showById'])->name('agencias.show');
        });
        Route::middleware(['throttle:api-dni', 'api.audit:dni', 'abilities:dni:consultar'])->group(function (): void {
            Route::get('/dni/{dni}', DniApiController::class)->name('dni.show');
        });
        Route::get('/ruc/buscar', RucSearchApiController::class)->middleware(['throttle:ruc-search', 'api.audit:ruc', 'abilities:ruc:buscar'])->name('ruc.search');
        Route::get('/ruc/{ruc}', RucApiController::class)->middleware(['throttle:ruc-lookup', 'api.audit:ruc', 'abilities:ruc:consultar'])->name('ruc.show');
        Route::middleware(['throttle:api', 'abilities:agencies:read'])->group(function (): void {
            Route::get('/agencies', [AgencyCatalogController::class, 'index'])->name('agencies.index');
            Route::get('/agencies/changes', AgencyChangesController::class)->name('agencies.changes');
            Route::get('/agencies/search', [AgenciesController::class, 'search'])->name('agencies.search');
            Route::get('/agencies/version', [AgenciesController::class, 'version'])->name('agencies.version');
            Route::get('/agencies/snapshot', [AgenciesController::class, 'snapshot'])->name('agencies.snapshot');
            Route::get('/catalog/metadata', CatalogMetadataController::class)->name('catalog.metadata');
            Route::get('/agencies/{code}', [AgencyCatalogController::class, 'show'])->name('agencies.show');
        });
        Route::get('/me', MeController::class)->middleware(['throttle:api', 'abilities:profile:read'])->name('me');
    });
});
