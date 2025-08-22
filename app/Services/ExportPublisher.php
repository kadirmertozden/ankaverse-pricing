<?php

namespace App\Services;

use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;

class ExportPublisher
{
    /**
     * ExportRun için XML üretir/yazar, storage_path'e kaydeder,
     * path'i public URL (token link) olarak günceller
     * ve XML içindeki <Product> sayısını product_count alanına yazar.
     */
    public function upload(ExportRun $run): ExportRun
    {
        // XML içeriğini üret
        $contents = $this->buildXmlForRun($run);

        // Ürün sayısını XML içinden say
        $count = $this->countProductsFromString($contents);

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
            'product_count'=> $count,
        ])->save();

        return $run->refresh();
    }

    /**
     * Var olan XML dosyasını token değişmeden, aynı storage_path'e üzerine yazar.
     * Yazdıktan sonra XML’den <Product> sayısını tekrar sayar ve product_count’u günceller.
     */
    public function overwriteXml(ExportRun $run, string $xml): void
    {
        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        if (!$run->storage_path) {
            throw new \RuntimeException('storage_path boş: overwrite yapılamaz.');
        }

        // Dosyayı yaz
        Storage::disk($disk)->put($run->storage_path, $xml);

        // Say ve kaydet
        $count = $this->countProductsFromString($xml);
        $run->product_count = $count;
        $run->save();
    }

    /**
     * Mevcut kaydın storage’taki dosyasını okuyup ürün sayısını tekrar hesaplar.
     * (İsteğe bağlı: toplu bakım/yeniden sayım senaryolarında kullanışlıdır.)
     */
    public function recountFromStorage(ExportRun $run): int
    {
        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        if (!$run->storage_path || !Storage::disk($disk)->exists($run->storage_path)) {
            throw new \RuntimeException('XML dosyası bulunamadı.');
        }

        $xml = Storage::disk($disk)->get($run->storage_path);
        $count = $this->countProductsFromString($xml);

        $run->product_count = $count;
        $run->save();

        return $count;
    }

    /**
     * XML içindeki <Product> elemanlarını sayar.
     * Tercihen XMLReader kullanır, gerekirse basit bir fallback uygular.
     */
    protected function countProductsFromString(string $xml): int
    {
        $xml = trim($xml);
        if ($xml === '') {
            return 0;
        }

        // 1) XMLReader ile hızlı/sağlam sayım
        $count = 0;
        $reader = new \XMLReader();

        // Uyarıları bastırmak için NOWARNING/NOERROR kullanıyoruz;
        // içerik kötü formatlıysa fallback'e geçeceğiz.
        $ok = @$reader->XML($xml, null,
            LIBXML_NONET
            | LIBXML_NOENT
            | LIBXML_NOWARNING
            | LIBXML_NOERROR
        );

        if ($ok) {
            try {
                while (@$reader->read()) {
                    if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Product') {
                        $count++;
                    }
                }
            } finally {
                $reader->close();
            }
            // XMLReader ile sayabildiysek sonucu döndür
            return $count;
        }

        // 2) Fallback: basit ve dayanıklı bir yaklaşım
        // (Eleman adı duyarlı; false positive’i engellemek için <Product> açılışlarını sayıyoruz)
        // Not: <Product ...> gibi varyantlar için regex.
        if (preg_match_all('/<\s*Product(\s+[^>]*)?>/i', $xml, $m)) {
            return count($m[0]);
        }

        return 0;
    }

    /** 
     * Projede var olan XML builder’ı kullanarak içerik üretir.
     * Burayı projenizdeki gerçek sınıf/metoda göre gerekirse uyarlayın.
     */
    protected function buildXmlForRun(ExportRun $run): string
    {
        // Projede XmlExportBuilder varsa onu kullan
        if (class_exists(\App\Services\XmlExportBuilder::class)) {
            $builder = app(\App\Services\XmlExportBuilder::class);

            if (method_exists($builder, 'buildForRun')) {
                return $builder->buildForRun($run);
            }
            if (method_exists($builder, 'buildToString')) {
                $profile = method_exists($run, 'exportProfile') ? $run->exportProfile : null;
                return $builder->buildToString($profile, $run);
            }
            if (method_exists($builder, 'build')) {
                $profile = method_exists($run, 'exportProfile') ? $run->exportProfile : null;
                return $builder->build($profile);
            }
        }

        // Buraya düşüyorsa, hangi builder metodunu kullandığını bana yaz; ona göre uyarlayayım.
        throw new \RuntimeException('XmlExportBuilder bulunamadı veya beklenen metodlara sahip değil.');
    }
}
