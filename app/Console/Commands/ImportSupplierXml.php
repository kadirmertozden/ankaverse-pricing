<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportSupplierXml extends Command
{
    protected $signature = 'import:supplier
        {--url= : XML feed URL\'i (örn: https://yenitoptanci.com/xxxx)}
        {--file= : Yerel XML yolu (örn: storage/app/private/supplier.xml)}
        {--chunk=500 : Kaç üründe bir toplu upsert yapılsın}';

    protected $description = 'Tedarikçi XML ürünlerini bellek dostu şekilde içe aktarır / günceller.';

    /** Kaynak XML dosyasını indirin/yerelleyin ve tam dosya yolunu döndürün */
    protected function resolveSourcePath(): string
    {
        $dst = Storage::path('private/supplier.xml');

        if ($url = $this->option('url')) {
            $this->info('XML indiriliyor: '.$url);
            $res = Http::timeout(180)->withHeaders([
                'User-Agent' => 'LaravelImporter/1.0',
                'Accept'     => 'application/xml,text/xml,*/*',
            ])->get($url);

            if (!$res->ok()) {
                throw new \RuntimeException("URL indirilemedi. HTTP ".$res->status());
            }
            Storage::put('private/supplier.xml', $res->body());
            $this->line('Kaydedildi: '.$dst);
            return $dst;
        }

        if ($file = $this->option('file')) {
            if (!is_file($file)) {
                throw new \InvalidArgumentException("Yerel dosya bulunamadı: $file");
            }
            Storage::put('private/supplier.xml', file_get_contents($file));
            $this->line('Kaydedildi: '.$dst);
            return $dst;
        }

        // Varsayılan: daha önce kaydedilmiş dosya
        if (!is_file($dst)) {
            throw new \InvalidArgumentException("Kaynak yok. --url veya --file verin ya da $dst oluşturun.");
        }

        return $dst;
    }

    public function handle(): int
    {
        libxml_use_internal_errors(true);

        try {
            $path  = $this->resolveSourcePath();
            $chunk = max(1, (int)$this->option('chunk'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $xr = new \XMLReader();
        if (!$xr->open($path, null, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT)) {
            $this->error('XML açılamadı: '.$path);
            return self::FAILURE;
        }

        $total = 0;
        $batch = [];

        $this->info('İçe aktarma başladı…');

        while ($xr->read()) {
            if ($xr->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            // <Product> veya <product>
            if (strcasecmp($xr->name, 'Product') !== 0) {
                continue;
            }

            // Bu node’u tek başına SimpleXMLElement’e dönüştür
            $xmlString = $xr->readOuterXML();
            if ($xmlString === '' || $xmlString === false) {
                continue;
            }

            try {
                $node = new \SimpleXMLElement($xmlString);
            } catch (\Throwable $e) {
                // Bozuk node’u atla
                continue;
            }

            $row = $this->mapProduct($node);

            if ($row === null) {
                // zorunlu alanlardan biri yoksa atla
                continue;
            }

            $batch[] = $row;
            $total++;

            // CHUNK dolduysa yaz
            if (count($batch) >= $chunk) {
                $this->upsertBatch($batch);
                $this->line("↳ yazıldı: +".count($batch));
                $batch = [];
            }
        }

        // kalanlar
        if (!empty($batch)) {
            $this->upsertBatch($batch);
            $this->line("↳ yazıldı: +".count($batch));
        }

        $xr->close();

        $this->info("Bitti. Toplam: {$total}");
        return self::SUCCESS;
    }

    /** XML Product node -> DB row */
    protected function mapProduct(\SimpleXMLElement $p): ?array
    {
        // feed’de gördüğümüz alan adları
        $stockCode   = $this->txt($p->ProductCode ?? null);
        $productName = $this->txt($p->ProductName ?? null);

        if (!$stockCode || !$productName) {
            return null;
        }

        $category    = $this->txt($p->Category ?? null);
        $currency    = $this->txt($p->Currency ?? null);
        $descRaw     = $this->txt($p->Description ?? null, allowHtml: true);
        $price1      = $this->num($p->Price1 ?? null);
        $qty         = $this->int($p->Quantity ?? null);
        $tax         = $this->txt($p->TaxRate ?? null);
        $volume      = $this->txt($p->Volume ?? null);
        $gtin        = $this->txt($p->Gtin ?? null); // varsa

        // Görselleri toparla (hem düz metin hem alt tag’ler)
        $images = $this->collectImages($p->Images ?? null);

        // Açıklama HTML normalize
        $description = $this->normalizeDescription($descRaw);

        // Sütun eşleşmeleri – projendeki kolonlara göre uyarlayabilirsin
        $now = now();

        return [
            // Upsert eşsiz anahtar: stock_code
            'stock_code'        => $stockCode,
            'name'              => $productName,
            'brand'             => null, // feed vermiyor
            'category_path'     => $category ?: null,
            'stock_amount'      => $qty,
            'currency_code'     => $currency ?: null,
            'vat_rate'          => $tax ?: null,
            'gtin'              => $gtin ?: null,
            'images'            => $images ? json_encode($images, JSON_UNESCAPED_SLASHES) : null,
            'description'       => $description,
            'buy_price_vat'     => $price1 !== null ? number_format($price1, 2, '.', '') : '0.00',
            'commission_rate'   => 0,
            'width'             => '0.00',
            'length'            => '0.00',
            'height'            => '0.00',
            'volumetric_weight' => $volume ?: null,
            'updated_at'        => $now,
            'created_at'        => $now, // upsert created_at’ı korur/ayarlar
        ];
    }

    /** Toplu upsert – Laravel 8+ */
    protected function upsertBatch(array $rows): void
    {
        // products tablosunda stock_code unique olmalı
        DB::table('products')->upsert(
            $rows,
            ['stock_code'], // conflict key
            [
                'name','brand','category_path','stock_amount','currency_code','vat_rate','gtin',
                'images','description','buy_price_vat','commission_rate','width','length','height',
                'volumetric_weight','updated_at'
            ]
        );
    }

    /** Metin çıkar (trim), istersen HTML’e izin ver */
    protected function txt($val, bool $allowHtml = false): ?string
    {
        if ($val === null) return null;
        $s = (string)$val;

        if ($s === '') return null;

        if ($allowHtml) {
            // CDATA vb. kalsın, ama baş/son boşluk temizle
            return trim($s);
        }
        // düz metin
        return trim(strip_tags($s));
    }

    /** Tam sayı */
    protected function int($val): int
    {
        $s = $this->txt($val) ?? '0';
        return (int)preg_replace('/[^\d\-]/', '', $s);
    }

    /** Ondalık sayı */
    protected function num($val): ?float
    {
        $s = $this->txt($val);
        if ($s === null) return null;

        // 1.234,56 veya 1,234.56 gibi yazımlar olabilir
        $s = str_replace([' ', "\u{00A0}"], '', $s);
        // virgül ondalıksa düzelt
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d+,\d+$/', $s)) {
            $s = str_replace(',', '.', $s);
        } else {
            // binlik ayraçları kaldır
            $s = preg_replace('/(?<=\d)[,](?=\d{3}(\D|$))/', '', $s);
        }

        if (!is_numeric($s)) return null;
        return (float)$s;
    }

    /** Images alanını çok biçimli yakala */
    protected function collectImages($imagesNode): array
    {
        $out = [];

        if ($imagesNode === null) return $out;

        // <Images> düz metin ise: virgül/; ile ayrılmış olabilir
        $raw = (string)$imagesNode;
        if ($raw !== '' && strip_tags($raw) === $raw) {
            foreach (preg_split('/[;,]/', $raw) as $u) {
                $u = trim($u);
                if ($this->isUrl($u)) $out[] = $u;
            }
        }

        // Alt elemanlar varsa (<Image1>..</Image1>)
        if ($imagesNode instanceof \SimpleXMLElement) {
            foreach ($imagesNode->children() as $img) {
                $u = trim((string)$img);
                if ($this->isUrl($u)) $out[] = $u;
            }
        }

        // tekilleştir
        $out = array_values(array_unique($out));
        return $out;
    }

    protected function isUrl(string $s): bool
    {
        if ($s === '') return false;
        // bazı feed’lerde http(s) zorunlu
        return (bool)filter_var($s, FILTER_VALIDATE_URL);
    }

    /** Basit HTML normalize (paragrafları koru) */
    protected function normalizeDescription(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return null;

        // Yaygın kötü karakterleri sadeleştir
        $clean = preg_replace("/\r\n|\r|\n/", "\n", $html);
        // Çift noktalı liste ayırıcıları vs. kalsın; gereksiz boşlukları azalt
        $clean = preg_replace('/[ \t]+/', ' ', $clean);
        $clean = trim($clean);

        return $clean;
    }
}
