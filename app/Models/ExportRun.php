<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ExportRun extends Model
{
public function getBasenameAttribute(): string
{
    return pathinfo($this->path ?? '', PATHINFO_FILENAME) ?: '';
}

public function getPublicUrlAttribute(): string
{
    $base = $this->basename;
    return $base ? url($base . '.xml') : '';
}

public function getDownloadUrlAttribute(): string
{
    return $this->public_url ? ($this->public_url . '?dl=1') : '';
}

// İstersen otomatik eklensin
// protected $appends = ['basename', 'public_url', 'download_url'];


}
