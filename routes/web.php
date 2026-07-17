<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Admin\Agencies\Index as AgenciesIndex;
use App\Livewire\PublicAgencies\Index as PublicAgenciesIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->middleware(['auth'])->name('dashboard');
Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::view('/404', 'errors.404')->name('error.404');
Route::get('/admin/agencies', AgenciesIndex::class)->middleware(['auth'])->name('admin.agencies.index');
Route::get('/agencies', PublicAgenciesIndex::class)->name('public.agencies.index');
