<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Not: Token route'u EN ALTA koyduk ki diğer path'leri gölgelemesin.
| 'admin' ve diğer tüm route'lar bunun üstünde olmalı.
*/

// (Opsiyonel) Anasayfa vb. kendi route'ların burada olabilir.
// Route::get('/', fn () => view('welcome'))->name('home');

// Admin'den imzalı indirme linki (10 dk gibi bir süreyle oluşturuluyor).
Route::get('/admin/exports/{run}/download', [ExportController::class, 'adminDownload'])
    ->middleware('signed')
    ->name('exports.download');

/*
|---------------------------------------------------------------------------
| Public XML
|---------------------------------------------------------------------------
| Token'ı bilen HERKES erişebilir. is_public kontrolü YOK.
| Regex: 16–64 karakter uzunlukta a–z, A–Z, 0–9 (ör: T2HADRSQFJTMWFGXFNAER48DAW)
*/
Route::get('/{token}', [ExportController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{16,64}')
    ->name('exports.public');
