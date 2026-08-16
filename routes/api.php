<?php

use App\Http\Controllers\Api\V1\AgenciesController;
use App\Http\Controllers\Api\V1\AgencyCatalogController;
use App\Http\Controllers\Api\V1\AgencyChangesController;
use App\Http\Controllers\Api\V1\CatalogMetadataController;
use App\Http\Controllers\Api\V1\DeclarationController;
use App\Http\Controllers\Api\V1\DniApiController;
use App\Http\Controllers\Api\V1\ExtensionChromeConfigController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Integrations\IntegrationDiscoveryController;
use App\Http\Controllers\Api\V1\Integrations\N8nTelegramPersonalCodeController;
use App\Http\Controllers\Api\V1\Integrations\N8nTokenRequestController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ShalomRecordarAuthController;
use App\Http\Controllers\Api\V1\SystemVersionController;
use App\Http\Controllers\Api\V1\TokenRequestController as PublicTokenRequestController;
use App\Http\Controllers\Api\V1\TokenRotationRequestController;
use App\Modules\Ruc\Http\Controllers\RucApiController;
use App\Modules\Ruc\Http\Controllers\RucSearchApiController;
use App\Modules\Shalom\Http\Controllers\DeliveryRecordsExportController;
use App\Modules\Shalom\Http\Controllers\ShalomSyncController;
use App\Modules\Shalom\Http\Middleware\AuthenticateShalomApiKey;
use App\Modules\ShalomRecordar\Http\Controllers\ShalomRecordarSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/version', SystemVersionController::class)->name('version');
    Route::get('/extension/chrome/config', ExtensionChromeConfigController::class)->middleware('throttle:api')->name('extension.chrome.config');
    Route::post('/shalom/sync', [ShalomSyncController::class, 'sync'])
        ->middleware(['throttle:100,1', AuthenticateShalomApiKey::class])
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
    Route::prefix('mobile')->name('mobile.')->group(function (): void {
        Route::post('/login', [MobileAuthController::class, 'login'])->middleware('throttle:api-mobile')->name('login');

        Route::middleware(['auth:sanctum', 'api.token-owner-active', 'throttle:api-mobile'])->group(function (): void {
            Route::get('/me', [MobileAuthController::class, 'me'])->name('me');
            Route::post('/logout', [MobileAuthController::class, 'logout'])->name('logout');
        });
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
            // Cierre de sesión de la extensión: revoca el token en uso sin
            // tocar los registros locales ni los ya sincronizados.
            Route::post('/auth/logout', [ShalomRecordarSyncController::class, 'logout'])
                ->middleware(['throttle:shalom-recordar'])
                ->name('auth.logout');
        });
        Route::middleware(['throttle:api-agencias', 'api.audit:agencias', 'abilities:agencias:consultar'])->group(function (): void {
            Route::get('/agencias', [AgencyCatalogController::class, 'index'])->name('agencias.index');
            Route::get('/agencias/{id}', [AgencyCatalogController::class, 'showById'])->name('agencias.show');
        });
        // api.delegate-user permite que el bridge Node de Declaración Jurada
        // (packages/shalom-declaracion-jurada) actúe en nombre del usuario que
        // tiene la sesión abierta allí, con su propio token técnico. Sin él, el
        // paquete React no tendría forma de atribuir cada declaración a su autor.
        Route::middleware(['throttle:api-declaraciones', 'api.audit:declaraciones', 'api.delegate-user', 'abilities:declaraciones:gestionar'])->group(function (): void {
            Route::get('/declarations', [DeclarationController::class, 'index'])->name('declarations.index');
            Route::post('/declarations', [DeclarationController::class, 'store'])->name('declarations.store');
            Route::get('/declarations/{id}', [DeclarationController::class, 'show'])->whereNumber('id')->name('declarations.show');
            Route::get('/declarations/{id}/pdf', [DeclarationController::class, 'pdf'])->whereNumber('id')->name('declarations.pdf');
        });
        // Centro de notificaciones de CodeRED Mobile. La ability `mobile` la
        // lleva todo token emitido por el login movil, asi que no hace falta un
        // permiso RBAC nuevo: cada quien lee lo suyo y nada mas. Los tokens
        // tecnicos (bridge React, n8n) no la tienen y quedan fuera.
        Route::middleware(['throttle:api-mobile', 'abilities:mobile'])->prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        });
        Route::middleware(['throttle:api-dni', 'api.audit:dni', 'api.delegate-user', 'abilities:dni:consultar'])->group(function (): void {
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
