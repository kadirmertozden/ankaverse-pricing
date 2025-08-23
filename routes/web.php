<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportRunController;

// Filament/Admin rotalarınız zaten panel tanımlarından geliyor.

// Public XML göster & indir
Route::get('{token}', [ExportRunController::class, 'show'])->name('exports.show');
Route::get('{token}/download', [ExportRunController::class, 'download'])->name('exports.download');
