<?php

namespace App\Services;

use App\Models\ExportRun;
use App\Models\ExportProfile;
use Illuminate\Support\Facades\Storage;

class ExportPublisher
{
    public function upload(ExportRun $run, ?string $contents = null): bool
    {
        $disk = 's3';
        $path = $run->path;

        if ($contents !== null) {
            Storage::disk($disk)->put($path, $contents);
        }

        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $run->is_public = true;
        $run->published_at = now();
        $run->save();

        return true;
    }

    public function delete(ExportRun $run): bool
    {
        $disk = 's3';
        $ok = true;

        if ($run->path) {
            try {
                Storage::disk($disk)->delete($run->path);
            } catch (\Throwable $e) {
                $ok = false; // dosya yoksa da akışı bozma
            }
        }

        // kayıt uygulamada dursun ama “yayında değil” işaretleyelim
        $run->is_public = false;
        $run->published_at = null;
        $run->save();

        return $ok;
    }

    /**
     * Profilden XML üret + S3'e yükle + ExportRun kaydı oluştur.
     */
    public function buildAndPublishFromProfile(ExportProfile $profile): ExportRun
    {
        // 1) XML oluştur (Sisteminde halihazırda ne varsa oraya bağla)
        // A) Zaten bir üretici servisin varsa:
        // $xml = app(\App\Services\YourExistingExportBuilder::class)->buildForProfile($profile);

        // B) Yoksa geçici (örnek) bir üretim:
        $xml = $this->fallbackXml($profile);

        // 2) Yol ve dosya adı
        $basename = now()->format('Ymd_His');       // 20250814_181200
        $path = "exports/{$profile->id}/{$basename}.xml";

        // 3) DB kaydı
        $run = new ExportRun();
        $run->export_profile_id = $profile->id;
        $run->path = $path;
        $run->status = 'manual';
        $run->product_count = 0; // istersen gerçek sayıyı üretici döndürsün
        $run->save();

        // 4) S3’e yaz + yayınla
        $this->upload($run, $xml);

        return $run;
    }

    private function fallbackXml(ExportProfile $profile): string
    {
        // Burayı gerçek XML üreticine bağlayabilirsin.
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
    <profile id="{$profile->id}" name="{$this->escape($profile->name)}"/>
</root>
XML;
    }

    private function escape(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
