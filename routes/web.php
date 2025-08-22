<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Token route'u EN ALTA koy ki diğer path'leri gölgelemesin.
*/

// Admin'den imzalı indirme linki
Route::get('/admin/exports/{run}/download', [ExportController::class, 'adminDownload'])
    ->middleware('signed')
    ->name('exports.download');

// Public XML – token'ı bilen HERKES erişir (is_public kontrolü yok)
Route::get('/{token}', [ExportController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{16,64}')
    ->name('exports.public');
