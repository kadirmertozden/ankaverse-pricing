<?php

namespace App\Console\Commands;

use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Throwable;

class ImportSupplierXml extends Command
{
    /**
     * Komut imzası:
     *  php artisan supplier:import {url}
     */
    protected $signature = 'supplier:import {url : XML feed URL}';

    protected $description = 'Tedarikçi XML\'ini indirip ürünleri veritabanına yazar.';

    public function handle(): int
    {
        $url = (string) $this->argument('url');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Geçersiz URL.');
            return self::INVALID;
        }

        try {
            $this->info("Downloading XML: {$url}");

            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Ankaverse-Importer/1.0',
                    'Accept'     => 'application/xml,text/xml,*/*',
                ],
            ]);

            $resp = $client->get($url);
            $xmlString = (string) $resp->getBody();

            // Ham XML'i kaydet
            Storage::put('supplier.xml', $xmlString);
            $this->info('Saved raw XML to storage/app/supplier.xml');

            // XML parse
            $xml = new SimpleXMLElement($xmlString);

            // Ürün düğümleri: hem Product hem product dene
            $nodes = $xml->xpath('//Product');
            if (!$nodes || count($nodes) === 0) {
                $nodes = $xml->xpath('//product');
            }
            if (!$nodes || count($nodes) === 0) {
                $this->warn('No <Product> nodes found via XPath //Product|//product');
                $this->line('Import finished. Total: 0');
                return self::SUCCESS;
            }

            // Yardımcılar
            $txt = fn($v) => is_string($v) ? trim($v) : trim((string) $v);

            $get = function ($node, array $candidates, $default = null) use ($txt) {
                foreach ($candidates as $tag) {
                    if (isset($node->$tag)) {
                        $val = $txt($node->$tag);
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
                return $default;
            };

            $num = function ($value, $default = 0.0) use ($txt) {
                if ($value === null) return $default;
                $s = $txt($value);
                if ($s === '' || $s === null) return $default;
                // boşluk ve NBSP temizle
                $s = str_replace(["\xC2\xA0", ' '], '', $s);
                // ondalık ayraç normalize
                $s = str_replace(',', '.', $s);
                // Binlik ayraçları olabilirse temizlemek için nokta/virgül kombinasyonlarını ele aldık
                // (yukarıdaki dönüşüm çoğu durumda yeterli)
                return is_numeric($s) ? (float) $s : $default;
            };

            $count = 0;

            foreach ($nodes as $p) {
                try {
                    // Çoklu adayla alan okumaları
                    $stockCode = $get($p, ['StockCode', 'ProductCode']);
                    $name      = $get($p, ['Name', 'ProductName']);

                    if (!$stockCode || !$name) {
                        // zorunlu iki alan yoksa bu ürünü atla
                        $this->warn("Skip: missing stockCode/name");
                        continue;
                    }

                    $buyPriceVat = $num($get($p, ['PriceInclusiveVat', 'PriceTL', 'Price', 'price'], 0), 0);
                    $brand       = $get($p, ['Brand', 'brand']);
                    $category    = $get($p, ['CategoryBreadCrumb', 'Category', 'categoryBreadCrumb']);
                    $currency    = $get($p, ['CurrencyCode', 'Currency'], 'TL');
                    $vatRate     = $num($get($p, ['VatRate', 'VAT', 'KDV']), null);

                    $width  = $num($get($p, ['Width', 'width']), 0);
                    $length = $num($get($p, ['Length', 'length']), 0);
                    $height = $num($get($p, ['Height', 'height']), 0);

                    $volumetric = $num($get($p, ['VolumetricWeight', 'CargoDesi', 'Desi']), null);
                    $stockAmt   = (int) $num($get($p, ['StockAmount', 'Stock', 'StockQty']), 0);
                    $gtin       = $get($p, ['Gtin','GTIN','EAN','Barcode']);

                    // Görseller
                    $images = [];
                    if (isset($p->Images)) {
                        foreach ($p->Images->children() as $img) {
                            $u = $txt($img);
                            if ($u) $images[] = $u;
                        }
                    } else {
                        foreach (['Image','Image1','ImageURL','ImageUrl'] as $tag) {
                            if (isset($p->$tag)) {
                                $u = $txt($p->$tag);
                                if ($u) $images[] = $u;
                            }
                        }
                    }

                    // Açıklama (HTML içerebilir)
                    $description = $get($p, ['Description','LongDescription','Desc']);

                    // Basit bir komisyon kuralı (istersen değiştir/sil)
                    $commission = $this->computeCommission($category, $brand);

                    Product::updateOrCreate(
                        ['stock_code' => $stockCode],
                        [
                            'name'              => $name,
                            'brand'             => $brand,
                            'category_path'     => $category,
                            'stock_amount'      => $stockAmt,
                            'currency_code'     => $currency,
                            'vat_rate'          => $vatRate,
                            'gtin'              => $gtin,
                            'images'            => $images ? json_encode($images) : null,
                            'description'       => $description,

                            'buy_price_vat'     => $buyPriceVat,
                            'commission_rate'   => $commission,

                            'width'             => $width,
                            'length'            => $length,
                            'height'            => $height,
                            'volumetric_weight' => $volumetric,
                        ]
                    );

                    $count++;
                } catch (Throwable $e) {
                    // tek tek ürün hatası Import’u durdurmasın
                    $this->warn("Row error for stock_code={$stockCode}: {$e->getMessage()}");
                }
            }

            $this->info("Import finished. Total: {$count}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Kategori/markaya göre basit komisyon kuralı.
     * İhtiyacına göre düzenle ya da 0 döndür.
     */
    private function computeCommission(?string $categoryPath, ?string $brand): int
    {
        $cat = mb_strtolower((string) $categoryPath);

        // örnek kurallar
        $map = [
            'kamp'        => 15,
            'bisiklet'    => 12,
            'av & balık'  => 14,
            'outdoor'     => 13,
            'fitness'     => 11,
        ];

        foreach ($map as $needle => $rate) {
            if ($cat !== '' && mb_strpos($cat, $needle) !== false) {
                return $rate;
            }
        }

        // markaya göre örnek
        if ($brand && mb_strtolower($brand) === 'ankaverse') {
            return 10;
        }

        return 0; // default
    }
}
