<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

class XmlNormalizer
{
    /**
     * Yüklenen her XML'i tek tip "<Products>...</Products>" köküne çevirir.
     * Root "Root>Products" veya doğrudan "Products" olabilir; hepsini normalize eder.
     */
    public static function normalizeProductsXml(string $xmlRaw): string
    {
        $xmlRaw = trim($xmlRaw);

        if ($xmlRaw === '') {
            return '<?xml version="1.0" encoding="UTF-8"?><Products/>';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        // Bazı dosyalarda BOM / encoding sorunları olabilir, hatayı yakala
        if (!@$doc->loadXML($xmlRaw, LIBXML_NOCDATA)) {
            // mümkün olduğunca kurtar
            $xmlRaw = preg_replace('/^[^\<]*/', '', $xmlRaw) ?? $xmlRaw;
            if (!@$doc->loadXML($xmlRaw, LIBXML_NOCDATA)) {
                return '<?xml version="1.0" encoding="UTF-8"?><Products/>';
            }
        }

        $rootName = $doc->documentElement?->nodeName ?? 'Products';

        // Zaten <Products> köküyse
        if (strcasecmp($rootName, 'Products') === 0) {
            return $doc->saveXML() ?: $xmlRaw;
        }

        $xp = new DOMXPath($doc);
        // Önce /Root/Products dene
        $productsNode = $xp->query('/Root/Products')->item(0) ?? null;

        // Bulamazsa her hangi bir Products düğümünü dene
        if (!$productsNode) {
            $nodes = $doc->getElementsByTagName('Products');
            if ($nodes->length > 0) {
                $productsNode = $nodes->item(0);
            }
        }

        $out = new DOMDocument('1.0', 'UTF-8');
        $out->formatOutput = true;
        $productsRoot = $out->createElement('Products');
        $out->appendChild($productsRoot);

        if ($productsNode) {
            foreach (iterator_to_array($productsNode->childNodes) as $child) {
                $productsRoot->appendChild($out->importNode($child, true));
            }
            return $out->saveXML();
        }

        // Son çare: bütün <Product> düğümlerini topla
        $productNodes = $doc->getElementsByTagName('Product');
        if ($productNodes->length > 0) {
            foreach ($productNodes as $p) {
                $productsRoot->appendChild($out->importNode($p, true));
            }
            return $out->saveXML();
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Products/>';
    }
}
