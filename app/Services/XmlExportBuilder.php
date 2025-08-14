<?php

namespace App\Services;

use App\Models\ExportProfile;
use Illuminate\Support\Str;
use XMLWriter;

class XmlExportBuilder
{
    public function buildToTempFile(ExportProfile $profile): array
    {
        if (! class_exists(XMLWriter::class)) {
            throw new \RuntimeException('PHP XMLWriter extension yüklü değil.');
        }

        // 1) tmp klasörünü garanti et
        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            if (! @mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
                throw new \RuntimeException("Temp klasörü oluşturulamadı: {$tmpDir}");
            }
        }

        // 2) yazılabilir mi?
        if (! is_writable($tmpDir)) {
            throw new \RuntimeException("Temp klasörü yazılabilir değil: {$tmpDir}");
        }

        // 3) tmp dosya yolu
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . 'xml_' . Str::uuid() . '.xml';

        // 4) XMLWriter ile yaz
        $x = new XMLWriter();
        if (! $x->openURI($tmpPath)) {
            throw new \RuntimeException("XMLWriter::openURI başarısız: {$tmpPath}");
        }

        $x->startDocument('1.0', 'UTF-8');
        $x->setIndent(true);
        $x->setIndentString('  ');

        $x->startElement('catalog');
        $x->writeAttribute('profile_id', (string) $profile->id);
        $x->writeAttribute('generated_at', now()->toIso8601String());

        $x->startElement('shop');
        $this->writeElement($x, 'name', $profile->name ?? 'ankaverse');
        $x->endElement(); // shop

        $x->startElement('products');

        $count = 0;

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

        // dosya var mı?
        if (! file_exists($tmpPath)) {
            throw new \RuntimeException("Temp XML dosyası oluşmadı: {$tmpPath}");
        }

        return ['tmp_path' => $tmpPath, 'count' => $count];
    }

    private function writeProduct(XMLWriter $x, \App\Models\Product $p, ExportProfile $profile): void
    {
        $x->startElement('product');
        $x->writeAttribute('id', (string) $p->id);
        $x->writeAttribute('sku', (string) $p->sku);

        $this->writeElement($x, 'name', $p->name);
        $this->writeElement($x, 'description', strip_tags($p->description ?? ''));

        $price = $p->price;
        $this->writeElement($x, 'price', number_format((float) $price, 2, '.', ''));
        $this->writeElement($x, 'stock', (string) ($p->stock ?? 0));

        if ($p->brand) {
            $x->startElement('brand');
            $this->writeElement($x, 'name', $p->brand->name);
            $x->endElement();
        }

        if ($p->relationLoaded('categories')) {
            $x->startElement('categories');
            foreach ($p->categories as $c) {
                $this->writeElement($x, 'category', $c->name);
            }
            $x->endElement();
        }

        if ($p->relationLoaded('images')) {
            $x->startElement('images');
            foreach ($p->images as $img) {
                $this->writeElement($x, 'image', $img->url);
            }
            $x->endElement();
        }

        // varyant örneği
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
                    $x->endElement();
                }
                $x->endElement();
            }
        }

        $x->endElement(); // product
    }

    private function writeElement(XMLWriter $x, string $name, ?string $value): void
    {
        $value = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $x->startElement($name);
        $x->text($value);
        $x->endElement();
    }
}
