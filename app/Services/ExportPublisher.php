<?php

namespace App\Services;

use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;

class ExportPublisher
{
    /**
     * ExportRun için XML üretir/yazar, storage_path'e kaydeder ve
     * path'i public URL (token link) olarak günceller.
     */
    public function upload(ExportRun $run): ExportRun
    {
        // XML içeriğini üret
        $contents = $this->buildXmlForRun($run);

        // Hangi diske yazılacak?
        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        // Fiziksel yazım yolu (yoksa yeni oluştur)
        $storagePath = $run->storage_path ?: sprintf(
            'exports/%d/%s.xml',
            $run->export_profile_id ?? 0,
            now()->format('Ymd_His')
        );

        // Yaz
        Storage::disk($disk)->put($storagePath, $contents);

        // Public URL: {XML_PUBLIC_BASE}/{token}
        $publicBase = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $publicUrl  = $publicBase . '/' . $run->publish_token;

        // Kayıt güncelle
        $run->fill([
            'storage_path' => $storagePath,
            'path'         => $publicUrl,   // Path artık tam public link
            'status'       => 'done',
            'published_at' => now(),
            'is_public'    => true,
        ])->save();

        return $run->refresh();
    }

    /**
     * Var olan XML dosyasını token değişmeden, aynı storage_path'e üzerine yazar.
     */
    public function overwriteXml(ExportRun $run, string $xml): void
    {
        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        if (!$run->storage_path) {
            throw new \RuntimeException('storage_path boş: overwrite yapılamaz.');
        }

        Storage::disk($disk)->put($run->storage_path, $xml);
    }

    /**
     * Projenizdeki mevcut builder ile uyumlu çalışmaya çalışır.
     * buildForRun / buildToString / build gibi yaygın isimleri dener.
     */
    protected function buildXmlForRun(ExportRun $run): string
    {
        // Eğer kendi XmlExportBuilder servisiniz varsa onu tercih edin:
        if (class_exists(\App\Services\XmlExportBuilder::class)) {
            $builder = app(\App\Services\XmlExportBuilder::class);

            // Yaygın imzaları sırasıyla dene:
            if (method_exists($builder, 'buildForRun')) {
                // Örn: buildForRun(ExportRun $run): string
                return $builder->buildForRun($run);
            }

            if (method_exists($builder, 'buildToString')) {
                // Örn: buildToString($profile, ?ExportRun $run = null): string
                $profile = method_exists($run, 'exportProfile') ? $run->exportProfile : null;
                return $builder->buildToString($profile, $run);
            }

            if (method_exists($builder, 'build')) {
                // Örn: build($profile): string
                $profile = method_exists($run, 'exportProfile') ? $run->exportProfile : null;
                return $builder->build($profile);
            }
        }

        // Buraya düşüyorsa projenizdeki builder imzasını bize haber verin:
        throw new \RuntimeException('XmlExportBuilder bulunamadı veya beklenen metodlara sahip değil.');
    }
}
