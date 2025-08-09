<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PriceController;

Route::get('/products', [PriceController::class, 'products']);
Route::post('/price/compute', [PriceController::class, 'compute']);
