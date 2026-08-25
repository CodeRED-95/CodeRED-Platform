<?php

use App\Http\Controllers\ApiDocumentationSpecController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Public\BuscadorShalomLegalController;
use App\Http\Controllers\Public\PublicTokenRequestController;
use App\Http\Middleware\EnsureApiDocumentationAccess;
use App\Livewire\Account\ChangePassword;
use App\Livewire\Account\Profile;
use App\Livewire\Admin\Agencies\Backups as AgencyBackups;
use App\Livewire\Admin\Agencies\Form as AgencyForm;
use App\Livewire\Admin\Agencies\Index as AgenciesIndex;
use App\Livewire\Admin\Agencies\Map as AgenciesMap;
use App\Livewire\Admin\Agencies\ShalomSync;
use App\Livewire\Admin\Agencies\ShalomSyncRun;
use App\Livewire\Admin\Agencies\Show as AgencyShow;
use App\Livewire\Admin\ApiDocumentation;
use App\Livewire\Admin\ApiTokenRequests\Index as ApiTokenRequestsIndex;
use App\Livewire\Admin\PermissionRequests\Index as PermissionRequestsIndex;
use App\Livewire\Admin\ApiTokens\Index as ApiTokensIndex;
use App\Livewire\Admin\ApiTools\DniTester;
use App\Livewire\Admin\ApiTools\RucTester;
use App\Livewire\Admin\DesignSystem;
use App\Livewire\Admin\Ruc\Records as RucRecords;
use App\Livewire\Admin\Ruc\Show as RucShow;
use App\Livewire\Admin\Settings\AgencyBackups as AgencyBackupSettings;
use App\Livewire\Admin\Settings\ApiDocumentation as ApiDocumentationSettings;
use App\Livewire\Admin\Settings\Dni as DniSettings;
use App\Livewire\Admin\Settings\N8n as N8nSettings;
use App\Livewire\Admin\Settings\ExtensionBlocking as ExtensionBlockingSettings;
use App\Livewire\Admin\Settings\Ubigeos as UbigeoSettings;
use App\Livewire\Admin\ShalomRecordar\Index as ShalomRecordarIndex;
use App\Livewire\Admin\ShalomRecordar\InstallationShow as ShalomRecordarInstallationShow;
use App\Livewire\Admin\ShalomRecordar\UserShow as ShalomRecordarUserShow;
use App\Livewire\Admin\Users\Form as UsersForm;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Show as UsersShow;
use App\Livewire\Dashboard;
use App\Livewire\PublicAgencies\Index as PublicAgenciesIndex;
use App\Livewire\PublicAgencies\Show as PublicAgencyShow;
use App\Modules\Agencies\Http\Controllers\AgencyBackupDownloadController;
use App\Modules\Agencies\Http\Controllers\AgencyExportController;
use App\Modules\Agencies\Http\Controllers\AgencyImportRunDownloadController;
use App\Modules\Agencies\Http\Controllers\AgencyMoveController;
use App\Modules\Ruc\Http\Controllers\RucBackupController;
use App\Modules\Ruc\Http\Controllers\RucBackupMultipartUploadController;
use App\Modules\Shalom\Http\Controllers\ShalomApiKeyController;
use App\Modules\Shalom\Livewire\Admin\DeliveryRecordsManager;
use Illuminate\Support\Facades\Route;

Route::get('/privacy/buscador-shalom', [BuscadorShalomLegalController::class, 'privacy'])->name('public.buscador-shalom.privacy');
Route::get('/support/buscador-shalom', [BuscadorShalomLegalController::class, 'support'])->name('public.buscador-shalom.support');

Route::get('/solicitar-token', [PublicTokenRequestController::class, 'create'])->middleware('throttle:public-token-request-form')->name('public.token-requests.create');
Route::post('/solicitar-token', [PublicTokenRequestController::class, 'store'])->middleware('throttle:public-token-requests')->name('public.token-requests.store');
Route::get('/', Dashboard::class)->middleware(['auth', 'home.landing'])->name('dashboard');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::view('/404', 'errors.404')->name('error.404');
Route::get('/admin/agencies', AgenciesIndex::class)->middleware(['auth'])->name('admin.agencies.index');
Route::get('/admin/agencies/export', AgencyExportController::class)->middleware(['auth'])->name('admin.agencies.export');
Route::get('/admin/agencies/backups', AgencyBackups::class)->middleware(['auth'])->name('admin.agencies.backups.index');
Route::get('/admin/agencies/backups/{backup}/download', AgencyBackupDownloadController::class)->middleware(['auth'])->name('admin.agencies.backups.download');
Route::get('/admin/agencies/map', AgenciesMap::class)->middleware(['auth'])->name('admin.agencies.map');
Route::get('/admin/agencies/import/shalom', ShalomSync::class)->middleware(['auth'])->name('admin.agencies.import.shalom');
Route::get('/admin/agencies/import/run/{importRun}', ShalomSyncRun::class)->middleware(['auth'])->name('admin.agencies.import.run');
Route::get('/admin/agencies/import/run/{importRun}/download/{file}', AgencyImportRunDownloadController::class)->middleware(['auth'])->whereIn('file', ['processed', 'report'])->name('admin.agencies.import.run.download');
Route::get('/admin/agencies/create', AgencyForm::class)->middleware(['auth'])->name('admin.agencies.create');
Route::get('/admin/agencies/{agency}/edit', AgencyForm::class)->middleware(['auth'])->whereNumber('agency')->name('admin.agencies.edit');
// whereNumber evita que un segmento no numérico (por ejemplo la ruta
// /admin/agencies/import ya retirada) llegue a la base de datos y provoque un
// error 500 en lugar de un 404 limpio.
Route::get('/admin/agencies/{agency}', AgencyShow::class)->middleware(['auth'])->whereNumber('agency')->name('admin.agencies.show');
Route::get('/admin/users', UsersIndex::class)->middleware(['auth'])->name('admin.users.index');
Route::get('/admin/users/create', UsersForm::class)->middleware(['auth'])->name('admin.users.create');
Route::get('/admin/users/{user}/edit', UsersForm::class)->middleware(['auth'])->name('admin.users.edit');
Route::get('/admin/users/{user}', UsersShow::class)->middleware(['auth'])->name('admin.users.show');
Route::middleware(EnsureApiDocumentationAccess::class)->group(function (): void {
    Route::get('/docs', ApiDocumentation::class)->name('docs');
    Route::get('/docs/api', ApiDocumentation::class)->name('api.docs');
    Route::get('/docs/api/v1', ApiDocumentation::class)->name('api.docs.v1');
    Route::get('/docs/api/agencias', ApiDocumentation::class)->name('api.docs.agencies');
    Route::get('/docs/api/dni', ApiDocumentation::class)->name('api.docs.dni');
    Route::get('/docs/api/autenticacion', ApiDocumentation::class)->name('api.docs.authentication');
    Route::get('/docs/api/errores', ApiDocumentation::class)->name('api.docs.errors');
    Route::get('/docs/api/ruc', ApiDocumentation::class)->name('api.docs.ruc');
    Route::get('/docs/api/ruc/autenticacion', ApiDocumentation::class)->name('api.docs.ruc.authentication');
    Route::get('/docs/api/ruc/errores', ApiDocumentation::class)->name('api.docs.ruc.errors');
    Route::get('/docs/api/ruc/ejemplos', ApiDocumentation::class)->name('api.docs.ruc.examples');
    Route::get('/docs/openapi', ApiDocumentation::class)->name('api.docs.openapi');
    Route::get('/docs/api/openapi.yaml', ApiDocumentationSpecController::class)->name('api.docs.spec');
});
Route::get('/admin/api-tokens', ApiTokensIndex::class)->middleware(['auth'])->name('admin.api-tokens.index');
Route::get('/admin/security/token-requests', ApiTokenRequestsIndex::class)->middleware(['auth'])->name('admin.api-token-requests.index');
Route::get('/admin/security/permission-requests', PermissionRequestsIndex::class)->middleware(['auth'])->name('admin.permission-requests.index');
Route::get('/admin/api-tools/dni', DniTester::class)->middleware(['auth'])->name('admin.api-tools.dni');
Route::get('/admin/api-tools/ruc', RucTester::class)->middleware(['auth', 'throttle:ruc-admin-test'])->name('admin.api-tools.ruc');
Route::get('/admin/ruc', RucRecords::class)->middleware(['auth'])->name('admin.ruc.records');
Route::get('/admin/ruc/backups', [RucBackupController::class, 'index'])->middleware(['auth'])->name('admin.ruc.backups');
Route::post('/admin/ruc/backups', [RucBackupController::class, 'store'])->middleware(['auth'])->name('admin.ruc.backups.store');
Route::post('/admin/ruc/backups/import', [RucBackupController::class, 'import'])->middleware(['auth'])->name('admin.ruc.backups.import');
Route::get('/admin/ruc/backups/{backup}/download', [RucBackupController::class, 'download'])->middleware(['auth'])->name('admin.ruc.backups.download');
Route::post('/admin/ruc/backups/{backup}/restore', [RucBackupController::class, 'restore'])->middleware(['auth'])->name('admin.ruc.backups.restore');
// Polling de progreso del restore (RestoreRucBackupJob, cola dedicada
// "ruc-backups") — GET simple, sin CSRF, cada 2-3s desde la UI.
Route::get('/admin/ruc/backups/operations/{operation}/status', [RucBackupController::class, 'operationStatus'])->middleware(['auth'])->name('admin.ruc.backups.operations.status');
Route::delete('/admin/ruc/backups/{backup}', [RucBackupController::class, 'destroy'])->middleware(['auth'])->name('admin.ruc.backups.destroy');
// Backups multipart (manifest.json + partes de RUC Tools) — consumidos por
// resources/js/ruc-backup-multipart-uploader.js, no por <form> tradicional
// (cada parte necesita ser un request HTTP independiente).
Route::post('/admin/ruc/backups/multipart', [RucBackupMultipartUploadController::class, 'store'])->middleware(['auth'])->name('admin.ruc.backups.multipart.store');
Route::get('/admin/ruc/backups/multipart/{upload}', [RucBackupMultipartUploadController::class, 'show'])->middleware(['auth'])->name('admin.ruc.backups.multipart.show');
Route::post('/admin/ruc/backups/multipart/{upload}/parts/{index}', [RucBackupMultipartUploadController::class, 'uploadPart'])->middleware(['auth'])->name('admin.ruc.backups.multipart.upload-part');
Route::delete('/admin/ruc/backups/multipart/{upload}', [RucBackupMultipartUploadController::class, 'destroy'])->middleware(['auth'])->name('admin.ruc.backups.multipart.destroy');
// whereNumber: {record} se resuelve por clave primaria (RucRecord::$id), así
// que solo puede ser numérico. Sin la restricción esta ruta actúa de comodín y
// captura cualquier /admin/ruc/<lo-que-sea> — una URL inexistente terminaba en
// HTTP 500 (model binding fallido) en vez del 404 que corresponde.
Route::get('/admin/ruc/{record}', RucShow::class)->middleware(['auth'])->whereNumber('record')->name('admin.ruc.show');
Route::get('/admin/shalom/entregas', DeliveryRecordsManager::class)->middleware(['auth'])->name('admin.shalom.delivery-records');
Route::get('/admin/shalom-recordar', ShalomRecordarIndex::class)->middleware(['auth'])->name('admin.shalom-recordar.index');
Route::get('/admin/shalom-recordar/users/{user}', ShalomRecordarUserShow::class)->middleware(['auth'])->name('admin.shalom-recordar.users.show');
Route::get('/admin/shalom-recordar/installations/{installation}', ShalomRecordarInstallationShow::class)->middleware(['auth'])->name('admin.shalom-recordar.installations.show');
Route::prefix('admin/shalom/api-keys')->middleware(['auth'])->name('admin.shalom.api-keys.')->group(function (): void {
    Route::get('/', [ShalomApiKeyController::class, 'index'])->name('index');
    Route::post('/', [ShalomApiKeyController::class, 'store'])->name('store');
    Route::get('{key}', [ShalomApiKeyController::class, 'show'])->name('show');
    Route::put('{key}', [ShalomApiKeyController::class, 'update'])->name('update');
    Route::delete('{key}', [ShalomApiKeyController::class, 'destroy'])->name('destroy');
    Route::post('{key}/revoke', [ShalomApiKeyController::class, 'revoke'])->name('revoke');
});
Route::get('/admin/settings/dni', DniSettings::class)->middleware(['auth'])->name('admin.settings.dni');
Route::get('/admin/settings/api-documentation', ApiDocumentationSettings::class)->middleware(['auth'])->name('admin.settings.api-documentation');
Route::get('/admin/settings/agency-backups', AgencyBackupSettings::class)->middleware(['auth'])->name('admin.settings.agency-backups');
Route::get('/admin/settings/ubigeos', UbigeoSettings::class)->middleware(['auth'])->name('admin.settings.ubigeos');
Route::get('/admin/settings/bloqueo-extension', ExtensionBlockingSettings::class)->middleware(['auth'])->name('admin.settings.extension-blocking');
Route::get('/admin/integrations/n8n', N8nSettings::class)->middleware(['auth'])->name('admin.integrations.n8n');
Route::get('/admin/design-system', DesignSystem::class)
    ->middleware(['auth'])
    ->name('admin.design-system');
Route::get('/agencies', PublicAgenciesIndex::class)->name('public.agencies.index');
Route::get('/agencies/{code}', PublicAgencyShow::class)->name('public.agencies.show');
Route::get('/profile', Profile::class)->middleware(['auth'])->name('profile.show');
Route::get('/account/change-password', ChangePassword::class)->middleware(['auth'])->name('account.change-password');
Route::post('/admin/agencies/{agency}/move', AgencyMoveController::class)->middleware(['auth'])->whereNumber('agency')->name('admin.agencies.move');
