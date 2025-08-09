<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class ImportSupplierXml extends Command
{
    protected $signature = 'supplier:import {url : XML feed URL}';
    protected $description = 'Imports or updates products from supplier XML feed';

    public function handle(): int
    {
        $url = $this->argument('url');
        $this->info("Downloading XML: $url");

        // XML yükle
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            foreach (libxml_get_errors() as $err) $this->error($err->message);
            return self::FAILURE;
        }

        // Farklı kök isimlerini karşılamak için basit bir keşif
        $products = $xml->Products?->Product ?? $xml->product ?? $xml->products?->product ?? [];
        $count = 0;

        foreach ($products as $p) {
            $stockCode = (string)($p->StockCode ?? $p->stockCode ?? '');
            if ($stockCode === '') continue;

            // Güvenli okuma helper’ları
            $str = fn($node, $alt=null) => isset($node) ? trim((string)$node) : $alt;
            $dec = fn($node, $alt=null) => isset($node) ? (float)$node : $alt;
            $int = fn($node, $alt=null) => isset($node) ? (int)$node : $alt;

            $images = [];
            if (isset($p->Images)) {
                foreach ($p->Images->children() as $img) {
                    $val = trim((string)$img);
                    if ($val !== '') $images[] = $val;
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
                ]
            );

            $count++;
            if ($count % 200 === 0) $this->info("Imported: $count");
        }

        $this->info("Import finished. Total: $count");
        return self::SUCCESS;
    }
}
