<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryMapping extends Model
{
    protected $fillable = [
        'marketplace_id','internal_category_path','marketplace_category_id',
    ];
}
