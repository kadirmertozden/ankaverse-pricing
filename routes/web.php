<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportRunController;

// (diğer rotalar)

// Herkese açık yayın (yalnızca 20–64 uzunlukta BÜYÜK HARF + RAKAM token)
Route::get('/{token}', [ExportRunController::class, 'show'])
    ->where('token', '^[A-Z0-9]{20,64}$')
    ->name('exports.show');

Route::get('/{token}/download', [ExportRunController::class, 'download'])
    ->where('token', '^[A-Z0-9]{20,64}$')
    ->name('exports.download');
