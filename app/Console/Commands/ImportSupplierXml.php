<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class ImportSupplierXml extends Command
{
    protected $signature = 'supplier:import {url : XML feed URL}';
    protected $description = 'Imports or updates products from supplier XML feed';

public function handle(): int
{
    $url = $this->argument('url');
    $this->info("Downloading XML: $url");

    try {
        $client = new Client(['timeout' => 30]);
        $res = $client->get($url, [
            'headers' => [
                'User-Agent' => 'AnkaversePricingBot/1.0 (+https://ankaverse-pricing)',
                'Accept' => 'application/xml,text/xml, */*;q=0.9',
            ],
        ]);
        $body = (string) $res->getBody();
    } catch (\Throwable $e) {
        $this->error("HTTP error: ".$e->getMessage());
        return self::FAILURE;
    }

    // Debug için kaydet
    Storage::put('supplier.xml', $body);
    $this->info('Saved raw XML to storage/app/supplier.xml (first 200 chars):');
    $this->line(substr($body, 0, 200));

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) {
        foreach (libxml_get_errors() as $err) $this->error($err->message);
        return self::FAILURE;
    }

    // Namespace’li durumlar için default ns’i kaydetmeye gerek kalmadan doğrudan XPath
    $nodes = $xml->xpath('//Product');
    if (!$nodes || count($nodes) === 0) {
        $this->warn('No <Product> nodes found via XPath //Product');
        return self::SUCCESS;
    }

    $count = 0;

    foreach ($nodes as $p) {
        $str = fn($node, $alt=null) => isset($node) ? trim((string)$node) : $alt;
        $dec = fn($node, $alt=null) => isset($node) ? (float)$node : $alt;
        $int = fn($node, $alt=null) => isset($node) ? (int)$node : $alt;

        $stockCode = $str($p->StockCode, '');
        if ($stockCode === '') continue;

        // Görseller
        $images = [];
        if (isset($p->Images)) {
            foreach ($p->Images->children() as $img) {
                $val = trim((string)$img);
                if ($val !== '') $images[] = $val;
            }
        }

        \App\Models\Product::updateOrCreate(
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