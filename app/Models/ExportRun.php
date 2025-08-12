<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExportRun extends Model
{
	protected $guarded = [];   // veya $fillable içine 'path' dâhil tüm alanları yaz
    protected $fillable = [
        'export_profile_id','status','path','product_count','error',
        'publish_token','is_public','published_at',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ExportRun $run) {
            if ($run->path && Storage::exists($run->path)) {
                Storage::delete($run->path);
            }
        });
    }

    public function exportProfile()
    {
        return $this->belongsTo(\App\Models\ExportProfile::class);
    }

    // herkese açık URL
public function getPublicUrlAttribute(): ?string
{
    if (! $this->is_public || ! $this->publish_token) return null;
    $base = config('app.url') ?: (request()?->getSchemeAndHttpHost() ?? '');
    return rtrim($base, '/').'/feeds/'.$this->publish_token.'.xml';
}

	
}
