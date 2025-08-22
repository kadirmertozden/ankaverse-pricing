<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportRun extends Model
{
    protected $table = 'export_runs';

    protected $fillable = [
        'export_profile_id',
        'status',
        'product_count',
        'is_public',
        'publish_token',
        'path',          // Public URL
        'storage_path',  // Fiziksel dosya yolu
        'published_at',
        'error',
        'name',
    ];

    protected $casts = [
        'is_public'    => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getPublicUrlAttribute(): ?string
    {
        return $this->path;
    }

    public function getStorageDiskAttribute(): string
    {
        return config('filesystems.default', 'public');
    }

    public function exportProfile()
    {
        return $this->belongsTo(\App\Models\ExportProfile::class, 'export_profile_id');
    }
}
