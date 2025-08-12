<?php

namespace App\Services\Export;

use App\Models\ExportProfile;
use App\Models\Product;
use App\Services\Pricing\PriceCalculator;

class HepsiburadaXmlBuilder
{
    public function build(ExportProfile $profile, $products): string
    {
        $calc = new PriceCalculator($profile);

        // XML kök
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Products/>');

        /** @var Product $p */
        foreach ($products as $p) {
            $data = $calc->for($p);

            $node = $xml->addChild('Product');

            // Zorunlu / temel alanlar
            $this->addChildSafe($node, 'Sku', (string)$p->sku);
            $this->addChildSafe($node, 'Name', (string)$p->name);
            $this->addChildSafe($node, 'CategoryId', (string)($data['marketplace_category_id'] ?? ''));
            $this->addChildSafe($node, 'Price', number_format($data['sell_price'], 2, '.', ''));
            $this->addChildSafe($node, 'Currency', (string)($p->currency ?: 'TRY'));
            $this->addChildSafe($node, 'Stock', (string)($p->stock ?? 0));

            // HB şeması için ekstra alanlar
            $this->addChildSafe($node, 'Brand', (string)($p->brand ?: 'Markasız'));     // Brand
            if (!empty($p->gtin)) {                                                     // GTIN (varsa)
                $this->addChildSafe($node, 'GTIN', (string)$p->gtin);
            }

            // Description (HTML barındırabilir → CDATA)
            $desc = $this->normalizeDescription((string)($p->description ?? ''));
            $this->addCdataChild($node, 'Description', $desc);

            // Images (JSON array bekleniyor)
            $images = $this->normalizeImages($p->images ?? []);
            if (!empty($images)) {
                $imagesNode = $node->addChild('Images');
                foreach ($images as $url) {
                    $this->addChildSafe($imagesNode, 'Image', $url);
                }
            }

            // (İsteğe bağlı) Ek alanlar: KDV, Komisyon, Kargo vs. log amaçlı
            // $this->addChildSafe($node, 'VatPercent', (string)($profile->vat_percent ?? 20));
            // $this->addChildSafe($node, 'CommissionPercent', (string)($data['commission_percent']));
            // $this->addChildSafe($node, 'ShippingCost', number_format($data['shipping'], 2, '.', ''));
        }

        // Güzel çıktı
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    /** Güvenli metin child (özel karakterleri encode eder) */
    private function addChildSafe(\SimpleXMLElement $parent, string $name, string $value): void
    {
        // SimpleXMLElement içerde htmlspecialchars yapar ama kontrol edelim
        $parent->addChild($name, htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /** CDATA child – açıklamalar için ideal */
    private function addCdataChild(\SimpleXMLElement $parent, string $name, string $value): void
    {
        $child = $parent->addChild($name);
        $node  = dom_import_simplexml($child);
        $node->appendChild($node->ownerDocument->createCDATASection($value));
    }

    /** Görselleri normalize et (string[]), boşları ele, tekrarı temizle, ilk 10’u al */
    private function normalizeImages($images): array
    {
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [$images];
        }
        if (!is_array($images)) {
            $images = [];
        }

        $images = array_values(array_unique(array_filter(array_map(function ($u) {
            $u = trim((string)$u);
            // Data URI veya boşları at
            if ($u === '' || str_starts_with($u, 'data:')) return null;
            // Mutlak URL istemeyen pazaryerleri olabilir ama HB genelde mutlak ister
            return $u;
        }, $images))));

        // HB genelde 8-10 görseli yeterli bulur
        return array_slice($images, 0, 10);
    }

    /** Açıklamayı sadeleştir, çok uzun ise kısalt (isteğe bağlı) */
    private function normalizeDescription(string $desc): string
    {
        $desc = trim($desc);
        // İstersen HTML'yi stripleyip basit metne çevirebilirsin:
        // $desc = strip_tags($desc);
        // HB HTML destekliyor; biz CDATA verdiğimiz için raw bırakıyoruz.
        // Çok uzun açıklamaları budamak istersen:
        // if (mb_strlen($desc) > 8000) $desc = mb_substr($desc, 0, 8000);
        return $desc;
    }
}
