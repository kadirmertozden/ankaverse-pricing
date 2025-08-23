<?php

namespace App\Models;

use App\Support\XmlNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportRun extends Model
{
    use HasFactory;

    protected $table = 'export_runs';

    protected $fillable = [
        'name',
        'export_profile_id',
        'publish_token',
        'is_active',
        'xml',           // DB'de longText ise burada tutulabilir (varsayıyoruz)
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function exportProfile()
    {
        return $this->belongsTo(ExportProfile::class, 'export_profile_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $run) {
            if (empty($run->publish_token)) {
                $run->publish_token = Str::upper(Str::random(26));
            }
        });

        // Her save'de normalize et ve diske yaz
        static::saved(function (self $run) {
            if (!empty($run->xml) && !empty($run->publish_token)) {
                $normalized = XmlNormalizer::normalizeProductsXml($run->xml);
                $run->xml = $normalized; // DB'de de normal tutmak istersen

                $path = "exports/{$run->publish_token}.xml";
                Storage::disk('public')->put($path, $normalized);

                // Ayrı bir sütun kullanmıyorsan dosya yolu DB'ye yazmak şart değil.
                // $run->storage_path = $path; // sütunun varsa
                // $run->saveQuietly(); // tekrar tetiklememek için
            }
        });
    }
}
