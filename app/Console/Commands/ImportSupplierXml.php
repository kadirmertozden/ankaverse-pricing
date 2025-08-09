<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use App\Models\Product;

class ImportSupplierXml extends Command
{
    protected $signature = 'supplier:import {url : XML feed URL}';
    protected $description = 'Imports or updates products from supplier XML feed';

    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $this->info("Downloading XML: $url");

        try {
            $client = new Client(['timeout' => 30]);
            $res = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'AnkaversePricingBot/1.0',
                    'Accept'     => 'application/xml,text/xml;q=0.9,*/*;q=0.8',
                ],
            ]);
            $body = (string) $res->getBody();
        } catch (\Throwable $e) {
            $this->error('HTTP error: '.$e->getMessage());
            return self::FAILURE;
        }

        // Debug: ham XML’i kaydet ve ilk 200 karakterini yaz
        Storage::put('supplier.xml', $body);
        $this->line('Saved raw XML to storage/app/supplier.xml');
        $this->line(substr($body, 0, 200));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            foreach (libxml_get_errors() as $err) {
                $this->error($err->message);
            }
            return self::FAILURE;
        }

        // Kök/namespace fark etmeksizin Product düğümlerini bul
        $nodes = $xml->xpath('//Product') ?: [];
        if (count($nodes) === 0) {
            $this->warn('No <Product> nodes found via XPath //Product');
        }

        $count = 0;

        foreach ($nodes as $p) {
            $str = fn($node, $alt=null) => isset($node) ? trim((string)$node) : $alt;
            $dec = fn($node, $alt=null) => isset($node) ? (float)$node : $alt;
            $int = fn($node, $alt=null) => isset($node) ? (int)$node : $alt;

            $stockCode = $str($p->StockCode, '');
            if ($stockCode === '') {
                continue;
            }

            // Görselleri topla
            $images = [];
            if (isset($p->Images)) {
                foreach ($p->Images->children() as $img) {
                    $val = trim((string)$img);
                    if ($val !== '') {
                        $images[] = $val;
                    }
                }
            }

            Product::updateOrCreate(
                ['stock_code' => $stockCode],
                [
                    'name'              => $str($p->Name),
                    'brand'             => $str($p->Brand),
                    'category_path'     => $str($p->CategoryBreadCrumb),
                    'stock_amount'      => $int($p->StockAmount, 0),
                    'currency_code'     => $str($p->CurrencyCode, 'TL'),
                    'vat_rate'          => $dec($p->VatRate, 0.0),
                    'buy_price_vat'     => $dec($p->PriceInclusiveVat, 0.0),
                    'width'             => $dec($p->Width, 0.0),
                    'length'            => $dec($p->Length, 0.0),
                    'height'            => $dec($p->Height, 0.0),
                    'volumetric_weight' => $dec($p->VolumetricWeight, null),
                    'gtin'              => $str($p->Gtin),
                    'images'            => $images ? json_encode($images) : null,
                    'description'       => $str($p->Description),
					'commission_rate'  => 0, // TODO: Hepsiburada kategori eşleşmesi ile doldurulacak

                ]
            );

            $count++;
            if (($count % 200) === 0) {
                $this->info("Imported: $count");
            }
        }

        $this->info("Import finished. Total: $count");
        return self::SUCCESS;
    }
}
