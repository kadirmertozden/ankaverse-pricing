<?php

namespace App\Services;

class XmlMergeService
{
    // Ürün node'unda kullanılacak anahtar tag listesi
    private array $keyTags = ['StockCode','Sku','SKU','Id','ID','Barcode','Gtin','GTIN'];

    // Fiyat alanları (bulduğunu günceller, sıralı öncelik)
    private array $priceTags = ['PriceInclusiveVat','Price','CalculatedPrice'];

    // Stok alanları
    private array $stockTags = ['StockAmount','Stock'];

    public function merge(string $baseXml, string $incomingXml): string
    {
        // İçerikler boşsa korumacı davran
        $baseXml = trim($baseXml);
        $incomingXml = trim($incomingXml);
        if ($incomingXml === '') {
            // hiç yoksa mevcutu geri ver
            return $baseXml !== '' ? $baseXml : $this->emptyProducts();
        }

        // SimpleXML parse
        $base = $this->parseXml($baseXml ?: $this->emptyProducts());
        $inc  = $this->parseXml($incomingXml);

        // base tarafında Products düğümü
        $baseProducts = $this->ensureProductsNode($base);
        $incProducts  = $this->findProductsNode($inc);

        // Index: base ürünlerini anahtara göre map'le
        $index = [];
        foreach ($baseProducts->Product as $p) {
            $k = $this->extractKey($p);
            if ($k !== '') $index[$k] = $p;
        }

        // iterate incoming products
        foreach ($incProducts->Product as $newP) {
            $key = $this->extractKey($newP);
            if ($key === '') {
                continue; // anahtarsız ürünü atla
            }

            if (isset($index[$key])) {
                // var olan ürünü güncelle
                $this->updateStockAndPrice($index[$key], $newP);
            } else {
                // yeni ürün => base'e ekle (deep copy)
                $this->appendProduct($baseProducts, $newP);
            }
        }

        // pretty output
        return $this->toXmlString($base);
    }

    private function emptyProducts(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Root><Products/></Root>';
    }

    private function parseXml(string $xml)
    {
        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            throw new \RuntimeException('Geçersiz XML yüklendi.');
        }
        return $sx;
    }

    private function findProductsNode(\SimpleXMLElement $sx): \SimpleXMLElement
    {
        if (isset($sx->Products)) return $sx->Products;
        if (isset($sx->Root) && isset($sx->Root->Products)) return $sx->Root->Products;

        // farklı hiyerarşiler için esnek olalım
        $nodes = $sx->xpath('//Products');
        if ($nodes && isset($nodes[0])) return $nodes[0];

        // yoksa oluştur
        if (!isset($sx->Products)) $sx->addChild('Products');
        return $sx->Products;
    }

    private function ensureProductsNode(\SimpleXMLElement $sx): \SimpleXMLElement
    {
        // Root/Products veya düz Products destekle
        if (isset($sx->Products)) return $sx->Products;
        if (!isset($sx->Root)) $sx->addChild('Root');
        if (!isset($sx->Root->Products)) $sx->Root->addChild('Products');
        return $sx->Root->Products;
    }

    private function extractKey(\SimpleXMLElement $product): string
    {
        foreach ($this->keyTags as $t) {
            if (isset($product->{$t}) && trim((string)$product->{$t}) !== '') {
                return $this->normalizeKey((string)$product->{$t});
            }
        }
        return '';
    }

    private function normalizeKey(string $v): string
    {
        $v = strtoupper(trim($v));
        $v = preg_replace('/\s+/', '', $v);
        return $v;
    }

    private function updateStockAndPrice(\SimpleXMLElement $baseP, \SimpleXMLElement $newP): void
    {
        // stok
        foreach ($this->stockTags as $tag) {
            if (isset($newP->{$tag})) {
                $this->setOrCreate($baseP, $tag, (string)$newP->{$tag});
                break;
            }
        }
        // fiyat
        foreach ($this->priceTags as $tag) {
            if (isset($newP->{$tag})) {
                $this->setOrCreate($baseP, $tag, (string)$newP->{$tag});
                // PriceInclusiveVat yoksa Price güncellensin vb. sırayla break yapıyoruz
                break;
            }
        }
    }

    private function setOrCreate(\SimpleXMLElement $node, string $tag, string $value): void
    {
        if (isset($node->{$tag})) {
            $node->{$tag} = $value;
        } else {
            $node->addChild($tag, htmlspecialchars($value));
        }
    }

    private function appendProduct(\SimpleXMLElement $products, \SimpleXMLElement $incomingProduct): void
    {
        // basit deep copy
        $dom = dom_import_simplexml($products);
        $imp = $dom->ownerDocument->importNode(dom_import_simplexml($incomingProduct), true);
        $dom->appendChild($imp);
    }

    private function toXmlString(\SimpleXMLElement $sx): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($sx->asXML());
        return $dom->saveXML();
    }
}
