<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_code', 'name', 'buy_price_vat', 'commission_rate',
        'width', 'length', 'height'
    ];
	protected $casts = [
    'dims'    => 'array',
    'images'  => 'array',
    'is_active' => 'boolean',
];
}
