<?php

use App\Modules\Ruc\Http\Controllers\BackupDownloadController;
use App\Modules\Ruc\Http\Controllers\BackupUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function () {
    // Descargar backup
    Route::get('/backups/{backup}/download', [BackupDownloadController::class, 'download'])
        ->name('backups.download');

    // Listar backups
    Route::get('/backups/list', [BackupDownloadController::class, 'list'])
        ->name('backups.list');

    // Cargar backup
    Route::post('/backups/upload', [BackupUploadController::class, 'upload'])
        ->name('backups.upload');
});
