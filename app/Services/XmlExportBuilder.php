<?php

namespace App\Services;

use App\Models\ExportProfile;
use XMLWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Büyük kataloglarda bellek dostu çalışır (XMLWriter + temp dosya + stream).
 * Gerekirse mapping & filtreler profile'dan okunur.
 */
class XmlExportBuilder
{
    /**
     * XML’i geçici dosyaya yazar, stream olarak döner.
     * Dönüş: ['tmp_path' => local tmp absolute path, 'count' => int]
     */
    public function buildToTempFile(ExportProfile $profile): array
    {
        // 1) Geçici dosya (local)
        $tmpPath = storage_path('app/tmp/xml_' . Str::uuid() . '.xml');

        // 2) XMLWriter ile streaming
        $x = new XMLWriter();
        $x->openURI($tmpPath);
        $x->startDocument('1.0', 'UTF-8');
        $x->setIndent(true);
        $x->setIndentString('  ');

        // Kök
        $x->startElement('catalog');
        $x->writeAttribute('profile_id', (string) $profile->id);
        $x->writeAttribute('generated_at', now()->toIso8601String());

        // İsteğe göre mağaza bilgileri
        $x->startElement('shop');
        $this->writeElement($x, 'name', $profile->name ?? 'ankaverse');
        $x->endElement(); // shop

        // 3) Ürünleri sırayla yaz (chunk’lı)
        $x->startElement('products');

        $count = 0;

        // --- ÖRNEK: ürün modellerini kendi tablolarına göre değiştir ---
        // Burada Product, Brand, Category gibi ilişkileri EAGER LOAD önerilir.
        // Fiyat/stock, varyant, resim listelemelerini kendi şemanla doldur.
        \App\Models\Product::query()
            ->with(['brand', 'categories', 'images'])
            ->where('is_active', true)
            ->orderBy('id')
            ->chunk(1000, function ($products) use (&$count, $x, $profile) {
                foreach ($products as $p) {
                    $count++;
                    $this->writeProduct($x, $p, $profile);
                }
            });

        $x->endElement(); // products

        $x->endElement(); // catalog
        $x->endDocument();
        $x->flush();

        return ['tmp_path' => $tmpPath, 'count' => $count];
    }

    /**
     * Tek ürünü XML’e yazar – burayı kendi alanlarına göre özelleştir.
     */
    private function writeProduct(XMLWriter $x, \App\Models\Product $p, ExportProfile $profile): void
    {
        // İsteğe göre profile’a bağlı mapping kuralları:
        // $map = $profile->mapping ?? []; // JSON sütun olabilir
        // $currency = $profile->currency ?? 'TRY';

        $x->startElement('product');
        $x->writeAttribute('id', (string) $p->id);
        $x->writeAttribute('sku', (string) $p->sku);

        $this->writeElement($x, 'name', $p->name);
        $this->writeElement($x, 'description', strip_tags($p->description ?? ''));

        // Fiyat (örnek)
        $price = $p->price; // kendi sütunun
        $this->writeElement($x, 'price', number_format((float) $price, 2, '.', ''));

        // Stok
        $this->writeElement($x, 'stock', (string) ($p->stock ?? 0));

        // Marka
        if ($p->brand) {
            $x->startElement('brand');
            $this->writeElement($x, 'name', $p->brand->name);
            $x->endElement();
        }

        // Kategoriler
        if ($p->relationLoaded('categories')) {
            $x->startElement('categories');
            foreach ($p->categories as $c) {
                $this->writeElement($x, 'category', $c->name);
            }
            $x->endElement();
        }

        // Resimler
        if ($p->relationLoaded('images')) {
            $x->startElement('images');
            foreach ($p->images as $img) {
                $this->writeElement($x, 'image', $img->url); // URL alanına göre
            }
            $x->endElement();
        }

        // Varyantlar (varsa)
        if (method_exists($p, 'variants')) {
            $variants = $p->variants()->get();
            if ($variants->count()) {
                $x->startElement('variants');
                foreach ($variants as $v) {
                    $x->startElement('variant');
                    $x->writeAttribute('id', (string) $v->id);
                    $this->writeElement($x, 'sku', $v->sku);
                    $this->writeElement($x, 'price', number_format((float) ($v->price ?? $price), 2, '.', ''));
                    $this->writeElement($x, 'stock', (string) ($v->stock ?? 0));
                    $x->endElement(); // variant
                }
                $x->endElement(); // variants
            }
        }

        $x->endElement(); // product
    }

    /**
     * Güvenli element yaz (UTF-8 / XML1 escape).
     */
    private function writeElement(XMLWriter $x, string $name, ?string $value): void
    {
        $value = $value ?? '';
        // XML için kaçış
        $value = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $x->startElement($name);
        $x->text($value);
        $x->endElement();
    }
}
