<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Admin\Agencies\Index as AgenciesIndex;
use App\Livewire\PublicAgencies\Index as PublicAgenciesIndex;
use App\Modules\Agencies\Http\Controllers\AgencyImportPreviewController;
use App\Modules\Agencies\Http\Controllers\AgencyMoveController;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->middleware(['auth'])->name('dashboard');
Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::view('/404', 'errors.404')->name('error.404');
Route::get('/admin/agencies', AgenciesIndex::class)->middleware(['auth'])->name('admin.agencies.index');
Route::get('/agencies', PublicAgenciesIndex::class)->name('public.agencies.index');
Route::post('/admin/agencies/import/preview', AgencyImportPreviewController::class)->middleware(['auth'])->name('admin.agencies.import.preview');
Route::post('/admin/agencies/{agency}/move', AgencyMoveController::class)->middleware(['auth'])->name('admin.agencies.move');
