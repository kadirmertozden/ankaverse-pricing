<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use XMLReader;

class ImportSupplierXml extends Command
{
    protected $signature = 'import:supplier {url? : (opsiyonel) XML feed URL}';
    protected $description = 'Tedarikçi XML’ini stream ederek içeri alır ve products tablosuna upsert eder.';

    /** Kaç kayıtta bir toplu upsert yapılacak */
    private int $chunkSize = 1000;

    public function handle(): int
    {
        libxml_use_internal_errors(true);

        // 1) XML’i al: URL verilmişse indir, verilmediyse storage’daki mevcut dosyayı kullan
        $url = (string) $this->argument('url');
        $storagePath = 'supplier.xml';

        if ($url) {
            $this->info("Downloading XML: {$url}");
            try {
                $resp = Http::timeout(120)->withHeaders([
                    'Accept' => 'application/xml,text/xml,*/*',
                    'User-Agent' => 'ankaverse-pricing/1.0',
                ])->get($url);

                if (!$resp->ok()) {
                    $this->error("HTTP hata: {$resp->status()}");
                    return self::FAILURE;
                }

                Storage::put($storagePath, $resp->body());
                $this->line('Saved raw XML to '.Storage::path($storagePath));
            } catch (\Throwable $e) {
                $this->error('İndirme hatası: '.$e->getMessage());
                return self::FAILURE;
            }
        } else {
            if (!Storage::exists($storagePath)) {
                $this->error("XML bulunamadı: storage/app/{$storagePath}. Komutu URL ile çalıştır.");
                return self::FAILURE;
            }
            $this->line('Using existing '.Storage::path($storagePath));
        }

        // 2) XML’i stream ederek oku
        $filePath = Storage::path($storagePath);
        $reader = new XMLReader();
        if (!$reader->open($filePath)) {
            $this->error('XML açılamadı.');
            return self::FAILURE;
        }

        $rows = [];
        $count = 0;
        $commissionDefault = (int) (config('pricing.default_commission', 0));

        $this->info('Import started (stream mode)…');

        try {
            while ($reader->read()) {
                // Hem <Product> hem <product> yakala
                if (
                    $reader->nodeType === XMLReader::ELEMENT &&
                    (strcasecmp($reader->name, 'Product') === 0 || strcasecmp($reader->name, 'product') === 0)
                ) {
                    $xml = new SimpleXMLElement($reader->readOuterXML());

                    // Ortak getter
                    $g = static function (?SimpleXMLElement $x, string $k, $def = null) {
                        return ($x && isset($x->{$k})) ? trim((string) $x->{$k}) : $def;
                    };

                    // İki farklı şemayı tespit et
                    $hasA = $g($xml, 'StockCode') !== null || $g($xml, 'Name') !== null;              // Şema-A
                    $hasB = $g($xml, 'ProductCode') !== null || $g($xml, 'ProductName') !== null;     // Şema-B

                    // Ortak alanlar
                    $stockCode = null;
                    $name      = null;
                    $priceVat  = 0.0;
                    $stockAmt  = 0;
                    $currency  = null;
                    $vatRate   = null;
                    $category  = null;
                    $volume    = null;
                    $width = $length = $height = 0.0;
                    $brand = $gtin = $images = $desc = null;

                    // Şema-A: (ilk feed)
                    if ($hasA) {
                        $stockCode = $g($xml, 'StockCode');
                        $name      = $g($xml, 'Name');
                        $priceVat  = (float) ($g($xml, 'PriceInclusiveVat', 0));
                        $stockAmt  = (int)   ($g($xml, 'StockAmount', 0));
                        $currency  = $g($xml, 'CurrencyCode');
                        $vatRate   = $g($xml, 'VatRate');
                        $category  = $g($xml, 'CategoryBreadCrumb');
                        $volume    = $g($xml, 'VolumetricWeight');
                        $width     = (float) ($g($xml, 'Width', 0));
                        $length    = (float) ($g($xml, 'Length', 0));
                        $height    = (float) ($g($xml, 'Height', 0));
                        $brand     = $g($xml, 'Brand');
                        $gtin      = $g($xml, 'Gtin');

                        // Görseller farklı gelebilir
                        if ($g($xml, 'Images')) {
                            $images = (string) $g($xml, 'Images');
                        } elseif (isset($xml->Images)) {
                            // Image1, Image2… gibi alt elemanlar
                            $imgArr = [];
                            foreach ($xml->Images->children() as $img) {
                                $val = trim((string) $img);
                                if ($val !== '') $imgArr[] = $val;
                            }
                            $images = $imgArr ? json_encode($imgArr, JSON_UNESCAPED_SLASHES) : null;
                        }

                        $desc      = $g($xml, 'Description');
                    }

                    // Şema-B: (ikinci feed)
                    if ($hasB) {
                        $stockCode = $stockCode ?: $g($xml, 'ProductCode');
                        $name      = $name      ?: $g($xml, 'ProductName');

                        if (!$priceVat) { $priceVat = (float) ($g($xml, 'Price1', 0)); }
                        if (!$stockAmt) { $stockAmt = (int)   ($g($xml, 'Quantity', 0)); }

                        $currency  = $currency ?: $g($xml, 'Currency');
                        $vatRate   = $vatRate  ?: $g($xml, 'TaxRate');
                        $category  = $category ?: $g($xml, 'Category');
                        $volume    = $volume   ?: $g($xml, 'Volume');

                        // Bazı ikinci feed’lerde Images boş string olabiliyor — boşsa null bırak
                        $imgStr = $g($xml, 'Images');
                        $images = $images ?: ($imgStr !== '' ? $imgStr : null);

                        $desc    = $desc ?: $g($xml, 'Description');
                    }

                    // Zorunlu alan doğrulaması
                    if (!$stockCode || !$name) {
                        // eksik ise atla (loglamak istersen buraya yaz)
                        continue;
                    }

                    $rows[] = [
                        'stock_code'        => $stockCode,
                        'name'              => $name,
                        'buy_price_vat'     => $priceVat,
                        'commission_rate'   => $commissionDefault,
                        'width'             => $width,
                        'length'            => $length,
                        'height'            => $height,
                        'brand'             => $brand,
                        'category_path'     => $category,
                        'stock_amount'      => $stockAmt,
                        'currency_code'     => $currency,
                        'vat_rate'          => $vatRate,
                        'gtin'              => $gtin,
                        'images'            => $images,
                        'description'       => $desc,
                        'volumetric_weight' => $volume,
                        'updated_at'        => now(),
                        'created_at'        => now(),
                    ];

                    $count++;
                    if (count($rows) >= $this->chunkSize) {
                        $this->flushChunk($rows);
                        $rows = [];
                        $this->line("…{$count} kayıt işlendi");
                    }
                }
            }

            // son kalanlar
            if ($rows) {
                $this->flushChunk($rows);
                $rows = [];
            }

        } finally {
            $reader->close();
        }

        $this->info("Import finished. Total: {$count}");
        return self::SUCCESS;
    }

    /**
     * Chunk upsert
     */
    private function flushChunk(array $rows): void
    {
        // Not: index keys product.stock_code üzerinde olmalı (unique önerilir)
        DB::table('products')->upsert(
            $rows,
            ['stock_code'], // eşsiz anahtar
            [
                'name','buy_price_vat','commission_rate',
                'width','length','height',
                'brand','category_path','stock_amount',
                'currency_code','vat_rate','gtin','images',
                'description','volumetric_weight','updated_at'
            ]
        );
    }
}
