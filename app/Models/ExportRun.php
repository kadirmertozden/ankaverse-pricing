<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ExportRun extends Model
{


public function exportProfile()
{
    return $this->belongsTo(\App\Models\ExportProfile::class);
}

public function getBasenameAttribute(): string
{
    return pathinfo($this->path ?? '', PATHINFO_FILENAME) ?: '';
}

public function getPublicUrlAttribute(): string
{
    return $this->basename ? url($this->basename . '.xml') : '';
}

public function getDownloadUrlAttribute(): string
{
    return $this->public_url ? ($this->public_url . '?dl=1') : '';
}

// protected $appends = ['basename','public_url','download_url']; // istersen aç


// İstersen otomatik eklensin
// protected $appends = ['basename', 'public_url', 'download_url'];


}
