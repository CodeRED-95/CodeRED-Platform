<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Admin\Agencies\Index as AgenciesIndex;
use App\Livewire\Admin\Agencies\Form as AgencyForm;
use App\Livewire\Admin\Agencies\Show as AgencyShow;
use App\Livewire\Admin\Agencies\Import as AgencyImport;
use App\Livewire\PublicAgencies\Index as PublicAgenciesIndex;
use App\Livewire\PublicAgencies\Show as PublicAgencyShow;
use App\Modules\Agencies\Http\Controllers\AgencyImportPreviewController;
use App\Modules\Agencies\Http\Controllers\AgencyMoveController;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->middleware(['auth'])->name('dashboard');
Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::view('/404', 'errors.404')->name('error.404');
Route::get('/admin/agencies', AgenciesIndex::class)->middleware(['auth'])->name('admin.agencies.index');
Route::get('/admin/agencies/import', AgencyImport::class)->middleware(['auth'])->name('admin.agencies.import');
Route::get('/admin/agencies/create', AgencyForm::class)->middleware(['auth'])->name('admin.agencies.create');
Route::get('/admin/agencies/{agency}/edit', AgencyForm::class)->middleware(['auth'])->name('admin.agencies.edit');
Route::get('/admin/agencies/{agency}', AgencyShow::class)->middleware(['auth'])->name('admin.agencies.show');
Route::get('/agencies', PublicAgenciesIndex::class)->name('public.agencies.index');
Route::get('/agencies/{code}', PublicAgencyShow::class)->name('public.agencies.show');
Route::post('/admin/agencies/import/preview', AgencyImportPreviewController::class)->middleware(['auth'])->name('admin.agencies.import.preview');
Route::post('/admin/agencies/{agency}/move', AgencyMoveController::class)->middleware(['auth'])->name('admin.agencies.move');
