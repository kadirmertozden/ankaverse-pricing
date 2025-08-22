<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportRun extends Model
{
    // Toplu atamaya izin verilen alanlar
    protected $fillable = [
        'export_profile_id',
        'path',
        'xml_content',      // formda varsa (dehydrated=false ise DB'ye yazılmayabilir; yine de güvenli dursun)
        'status',
        'product_count',
        'is_public',
        'published_at',
        'publish_token',
        'error',
    ];

    // (İstersen "her şey serbest" dersen:)
    // protected $guarded = [];

    protected $casts = [
        'is_public'    => 'boolean',
        'published_at' => 'datetime',
        'product_count'=> 'integer',
    ];

    public function exportProfile(): BelongsTo
    {
        return $this->belongsTo(ExportProfile::class);
    }

    /** Yayın linki (public disk veya R2 CDN’e göre) */
    public function getPublicUrlAttribute(): ?string
    {
        if (! $this->path) return null;

        // R2/CDN tanımlıysa onu kullan
        $cdn = rtrim((string) env('CDN_PUBLIC_BASE', ''), '/');
        if ($cdn !== '') {
            return $cdn . '/' . ltrim($this->path, '/');
        }

        // public disk URL’si
        return \Storage::disk('public')->url($this->path);
    }

    /** İndir linki (kendi controller’ına gidiyorsa uyarlarsın) */
    public function getDownloadUrlAttribute(): ?string
    {
        // Basit: public URL’i kullan
        return $this->public_url;
        // Ya da route tabanlı indirme kullanacaksan:
        // return route('admin.exports.download', ['exportRun' => $this->id]);
    }
}
