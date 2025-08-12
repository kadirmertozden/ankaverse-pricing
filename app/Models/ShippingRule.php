<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRule extends Model
{
    protected $fillable = [
        'marketplace_id','desi_min','desi_max','weight_min','weight_max','price',
    ];
}
