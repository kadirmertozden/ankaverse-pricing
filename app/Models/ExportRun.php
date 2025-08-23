<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportRun extends Model
{
    protected $table = 'export_runs';

    protected $fillable = [
        'name',
        'publish_token',
        'storage_path',
        'product_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'product_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->publish_token)) {
                $model->publish_token = self::generateToken();
            }
            if ($model->is_active === null) {
                $model->is_active = true;
            }
        });
    }

    public static function generateToken(int $len = 26): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token = '';
        for ($i = 0; $i < $len; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $token;
    }

    public function getPublishUrlAttribute(): string
    {
        return route('exports.show', ['token' => $this->publish_token]);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('exports.download', ['token' => $this->publish_token]);
    }
}
