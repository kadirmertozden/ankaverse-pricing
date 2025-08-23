<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExportRun extends Model
{
    protected $table = 'export_runs';

    protected $fillable = [
        'name',
        'publish_token',
        'storage_path',
        'is_active',
        'export_profile_id',
        'source_url',
        'auto_sync',
        'sync_interval_minutes',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // token otomatik üretim (boşsa)
    protected static function booted()
    {
        static::creating(function (ExportRun $m) {
            if (empty($m->publish_token)) {
                // 26-32 char arası sağlam bir token
                $m->publish_token = strtoupper(Str::random(27));
            }
        });
    }

    public function publicUrl(): string
    {
        return url($this->publish_token);
    }

    public function downloadUrl(): string
    {
        return url($this->publish_token . '/download');
    }

    public function storageDisk(): string
    {
        return 'public';
    }

    public function storageExists(): bool
    {
        return $this->storage_path && Storage::disk($this->storageDisk())->exists($this->storage_path);
    }

    public function readXmlOrNull(): ?string
    {
        if (!$this->storageExists()) return null;
        return Storage::disk($this->storageDisk())->get($this->storage_path);
    }

    public function writeXml(string $xml): void
    {
        if (!$this->storage_path) {
            $this->storage_path = 'exports/' . $this->publish_token . '.xml';
        }
        Storage::disk($this->storageDisk())->put($this->storage_path, $xml);
        $this->save();
    }

    public function dueForSync(): bool
    {
        if (!$this->auto_sync) return false;
        if (!$this->source_url) return false;
        if ($this->sync_interval_minutes <= 0) return true;

        $last = $this->last_synced_at ?? Carbon::create(2000,1,1);
        return $last->addMinutes($this->sync_interval_minutes)->isPast();
    }
}
 