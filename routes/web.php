<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Livewire\Account\ChangePassword;
use App\Livewire\Admin\Agencies\Form as AgencyForm;
use App\Livewire\Admin\Agencies\Import as AgencyImport;
use App\Livewire\Admin\Agencies\Index as AgenciesIndex;
use App\Livewire\Admin\Agencies\Map as AgenciesMap;
use App\Livewire\Admin\Agencies\Show as AgencyShow;
use App\Livewire\Admin\DesignSystem;
use App\Livewire\Admin\Users\Form as UsersForm;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Show as UsersShow;
use App\Livewire\Dashboard;
use App\Livewire\PublicAgencies\Index as PublicAgenciesIndex;
use App\Livewire\PublicAgencies\Show as PublicAgencyShow;
use App\Modules\Agencies\Http\Controllers\AgencyImportPreviewController;
use App\Modules\Agencies\Http\Controllers\AgencyMoveController;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->middleware(['auth'])->name('dashboard');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::view('/404', 'errors.404')->name('error.404');
Route::get('/admin/agencies', AgenciesIndex::class)->middleware(['auth'])->name('admin.agencies.index');
Route::get('/admin/agencies/map', AgenciesMap::class)->middleware(['auth'])->name('admin.agencies.map');
Route::get('/admin/agencies/import', AgencyImport::class)->middleware(['auth'])->name('admin.agencies.import');
Route::get('/admin/agencies/create', AgencyForm::class)->middleware(['auth'])->name('admin.agencies.create');
Route::get('/admin/agencies/{agency}/edit', AgencyForm::class)->middleware(['auth'])->name('admin.agencies.edit');
Route::get('/admin/agencies/{agency}', AgencyShow::class)->middleware(['auth'])->name('admin.agencies.show');
Route::get('/admin/users', UsersIndex::class)->middleware(['auth'])->name('admin.users.index');
Route::get('/admin/users/create', UsersForm::class)->middleware(['auth'])->name('admin.users.create');
Route::get('/admin/users/{user}/edit', UsersForm::class)->middleware(['auth'])->name('admin.users.edit');
Route::get('/admin/users/{user}', UsersShow::class)->middleware(['auth'])->name('admin.users.show');
Route::get('/admin/design-system', DesignSystem::class)
    ->middleware(['auth'])
    ->name('admin.design-system');
Route::get('/agencies', PublicAgenciesIndex::class)->name('public.agencies.index');
Route::get('/agencies/{code}', PublicAgencyShow::class)->name('public.agencies.show');
Route::get('/account/change-password', ChangePassword::class)->middleware(['auth'])->name('account.change-password');
Route::post('/admin/agencies/import/preview', AgencyImportPreviewController::class)->middleware(['auth'])->name('admin.agencies.import.preview');
Route::post('/admin/agencies/{agency}/move', AgencyMoveController::class)->middleware(['auth'])->name('admin.agencies.move');
