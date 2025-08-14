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
    public function buildAndPublishFromProfile(\App\Models\ExportProfile $profile): \App\Models\ExportRun
{
    $builder = app(\App\Services\XmlExportBuilder::class);

    // 1) XML’i temp dosyaya yaz (streaming)
    $res = $builder->buildToTempFile($profile);
    $tmpPath = $res['tmp_path'];
    $count = $res['count'];

    // 2) Dosya adı & yol
    $basename = now()->format('Ymd_His'); // 20250814_181200
    $path = "exports/{$profile->id}/{$basename}.xml";

    // 3) DB kaydı
    $run = new \App\Models\ExportRun();
    $run->export_profile_id = $profile->id;
    $run->path = $path;
    $run->status = 'built';
    $run->product_count = $count;
    $run->save();

    // 4) S3’e stream yükle
    $stream = fopen($tmpPath, 'r');
    Storage::disk('s3')->writeStream($path, $stream);
    if (is_resource($stream)) fclose($stream);

    // 5) Temp dosyayı sil
    @unlink($tmpPath);

    // 6) Publish işaretleri
    $run->is_public = true;
    $run->published_at = now();
    $run->save();

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
