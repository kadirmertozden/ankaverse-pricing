<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $fillable = [
        'marketplace_id','marketplace_category_id','commission_percent',
    ];
}
