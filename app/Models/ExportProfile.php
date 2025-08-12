<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportProfile extends Model
{
    protected $fillable = [
        'name','marketplace_id','min_margin','commission_percent','vat_percent','rounding','is_active',
    ];

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function runs()
    {
        return $this->hasMany(ExportRun::class);
    }
}
