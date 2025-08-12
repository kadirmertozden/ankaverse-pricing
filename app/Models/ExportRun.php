<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ExportRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_public' => 'bool',
        'published_at' => 'datetime',
    ];

    // İlişki
    public function exportProfile()
    {
        return $this->belongsTo(ExportProfile::class, 'export_profile_id');
    }

    // Sadece yayınlanmış kayıtlar
    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_public', true);
    }

    // Admin tabloda / butonlarda görünen yayın linki
    public function getPublicUrlAttribute(): ?string
    {
        if (! $this->publish_token) {
            return null;
        }

        // route() .xml ile uyumlu çalışır
        return route('feeds.show', ['token' => $this->publish_token]);
    }
}
