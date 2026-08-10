<?php

use App\Http\Controllers\Api\V1\AgenciesController;
use App\Http\Controllers\Api\V1\AgencyCatalogController;
use App\Http\Controllers\Api\V1\AgencyChangesController;
use App\Http\Controllers\Api\V1\CatalogMetadataController;
use App\Http\Controllers\Api\V1\DniApiController;
use App\Http\Controllers\Api\V1\ExtensionChromeConfigController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Integrations\IntegrationDiscoveryController;
use App\Http\Controllers\Api\V1\Integrations\N8nTelegramPersonalCodeController;
use App\Http\Controllers\Api\V1\Integrations\N8nTokenRequestController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ShalomRecordarAuthController;
use App\Http\Controllers\Api\V1\SystemVersionController;
use App\Http\Controllers\Api\V1\TokenRequestController as PublicTokenRequestController;
use App\Http\Controllers\Api\V1\TokenRotationRequestController;
use App\Modules\Ruc\Http\Controllers\RucApiController;
use App\Modules\Ruc\Http\Controllers\RucSearchApiController;
use App\Modules\Shalom\Http\Controllers\ShalomSyncController;
use App\Modules\Shalom\Http\Controllers\DeliveryRecordsExportController;
use App\Modules\ShalomRecordar\Http\Controllers\ShalomRecordarSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/version', SystemVersionController::class)->name('version');
    Route::get('/extension/chrome/config', ExtensionChromeConfigController::class)->middleware('throttle:api')->name('extension.chrome.config');
    Route::post('/shalom/sync', [ShalomSyncController::class, 'sync'])
        ->middleware(['throttle:100,1', \App\Modules\Shalom\Http\Middleware\AuthenticateShalomApiKey::class])
        ->name('shalom.sync');
    Route::post('/token-requests', PublicTokenRequestController::class)->middleware('throttle:api')->name('token-requests.store');
    Route::get('/integrations/discovery', [IntegrationDiscoveryController::class, 'index'])->name('integrations.discovery');
    Route::post('/integrations/n8n/pair', [IntegrationDiscoveryController::class, 'pair'])->middleware('throttle:api')->name('integrations.n8n.pair');
    Route::post('/integrations/pair', [IntegrationDiscoveryController::class, 'pair'])->middleware('throttle:api')->name('integrations.pair');
    Route::middleware('integration.hmac')->prefix('integrations')->name('integrations.')->group(function (): void {
        Route::post('/discovery', [IntegrationDiscoveryController::class, 'register'])->name('discovery.register');
        Route::post('/heartbeat', [IntegrationDiscoveryController::class, 'heartbeat'])->name('heartbeat');
        Route::post('/challenge', [IntegrationDiscoveryController::class, 'challenge'])->name('challenge');
        Route::post('/rotate-secret', [IntegrationDiscoveryController::class, 'rotateSecret'])->name('secret.rotate');
        Route::post('/rotate-secret/confirm', [IntegrationDiscoveryController::class, 'confirmSecret'])->name('secret.confirm');
        Route::post('/disconnect', [IntegrationDiscoveryController::class, 'disconnect'])->name('disconnect');
    });
    Route::middleware('integration.hmac')->prefix('integrations/n8n')->name('integrations.n8n.')->group(function (): void {
        Route::post('/discovery', [IntegrationDiscoveryController::class, 'register'])->name('discovery.register');
        Route::post('/heartbeat', [IntegrationDiscoveryController::class, 'heartbeat'])->name('heartbeat');
        Route::post('/secret/rotate', [IntegrationDiscoveryController::class, 'rotateSecret'])->name('secret.rotate');
        Route::post('/secret/confirm', [IntegrationDiscoveryController::class, 'confirmSecret'])->name('secret.confirm');
        Route::post('/challenge', [IntegrationDiscoveryController::class, 'challenge'])->name('challenge');
        Route::post('/personal-code', [N8nTelegramPersonalCodeController::class, 'show'])->name('personal-code.show');
        Route::post('/token-requests/rotation-by-code', [N8nTelegramPersonalCodeController::class, 'rotation'])->name('token-requests.rotation-by-code');
        Route::post('/token-requests', [N8nTokenRequestController::class, 'store'])->name('token-requests.store');
        Route::get('/token-requests/{request_uuid}', [N8nTokenRequestController::class, 'show'])->name('token-requests.show');
        Route::post('/token-requests/{request_uuid}/retrieve', [N8nTokenRequestController::class, 'retrieve'])->name('token-requests.retrieve');
        Route::post('/token-requests/{request_uuid}/delivery', [N8nTokenRequestController::class, 'delivery'])->name('token-requests.delivery');
        Route::post('/token-requests/{request_uuid}/cancel', [N8nTokenRequestController::class, 'cancel'])->name('token-requests.cancel');
    });
    Route::prefix('shalom-recordar')->name('shalom-recordar.')->group(function (): void {
        Route::post('/auth/login', [ShalomRecordarAuthController::class, 'login'])
            ->middleware(['throttle:shalom-recordar'])
            ->name('auth.login');
    });
    Route::middleware(['auth:sanctum', 'api.token-owner-active', 'api.private-cache'])->group(function (): void {
        Route::prefix('shalom-recordar')->name('shalom-recordar.')->group(function (): void {
            Route::post('/installation', [ShalomRecordarSyncController::class, 'register'])
                ->middleware(['throttle:shalom-recordar'])
                ->name('installation.register');
            Route::post('/installations/register', [ShalomRecordarSyncController::class, 'register'])
                ->middleware(['throttle:shalom-recordar'])
                ->name('installations.register');
            Route::post('/sync', [ShalomRecordarSyncController::class, 'sync'])
                ->middleware(['throttle:shalom-recordar'])
                ->name('sync');
            Route::get('/sync/status', [ShalomRecordarSyncController::class, 'status'])
                ->middleware(['throttle:shalom-recordar'])
                ->name('sync.status');
        });
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
        Route::post('/token-requests/rotation', TokenRotationRequestController::class)->middleware(['throttle:api'])->name('token-requests.rotation');
        Route::get('/me', MeController::class)->middleware(['throttle:api', 'abilities:profile:read'])->name('me');
        Route::get('/admin/shalom/delivery-records/export', [DeliveryRecordsExportController::class, 'csv'])
            ->middleware(['throttle:api'])
            ->name('admin.shalom.delivery-records.export.csv');
    });
});
