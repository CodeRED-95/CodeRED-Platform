<?php

use App\Http\Controllers\Api\V1\AgenciesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/agencies', [AgenciesController::class, 'index']);
    Route::get('/agencies/search', [AgenciesController::class, 'search']);
    Route::get('/agencies/version', [AgenciesController::class, 'version']);
    Route::get('/agencies/snapshot', [AgenciesController::class, 'snapshot']);
    Route::get('/agencies/{code}', [AgenciesController::class, 'show']);
});
