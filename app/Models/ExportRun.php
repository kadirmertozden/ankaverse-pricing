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
        'path',          // Artık public URL tutulur (örn. https://xml.ankaverse.com.tr/TOKEN)
        'storage_path',  // Fiziksel dosya yolu (örn. exports/1/20250812_161733.xml)
        'published_at',
        'error',
        'name',
    ];

    protected $casts = [
        'is_public'    => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Public URL kısayolu (artık path zaten public URL)
     */
    public function getPublicUrlAttribute(): ?string
    {
        return $this->path;
    }

    /**
     * Dosyanın yazıldığı disk adı (config/filesystems.php).
     * Eğer R2/S3 kullanıyorsanız burada karar verebilirsiniz.
     */
    public function getStorageDiskAttribute(): string
    {
        return config('filesystems.default', 'public');
    }

    /**
     * (Opsiyonel) İlişkiler…
     */
    public function exportProfile()
    {
        return $this->belongsTo(\App\Models\ExportProfile::class, 'export_profile_id');
    }
}
