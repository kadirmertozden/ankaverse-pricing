<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportRun extends Model
{
    protected $fillable = [
        'export_profile_id',
        'path',
        'xml_content',
        'status',
        'product_count',
        'is_public',
        'published_at',
        'publish_token',
        'error',
    ];

    protected $casts = [
        'is_public'     => 'boolean',
        'published_at'  => 'datetime',
        'product_count' => 'integer',
    ];

    // NULL gelirse bile DB'ye giderken varsayılanları uygula
    protected $attributes = [
        'status'        => 'manual',
        'product_count' => 0,
        'is_public'     => false,
    ];

    public function exportProfile(): BelongsTo
    {
        return $this->belongsTo(ExportProfile::class);
    }

    public function getPublicUrlAttribute(): ?string
    {
        if (! $this->path) return null;

        $cdn = rtrim((string) env('CDN_PUBLIC_BASE', ''), '/');
        if ($cdn !== '') {
            return $cdn . '/' . ltrim($this->path, '/');
        }
        return \Storage::disk('public')->url($this->path);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return $this->public_url;
        // alternatif: route('admin.exports.download', ['exportRun' => $this->id]);
    }
}
