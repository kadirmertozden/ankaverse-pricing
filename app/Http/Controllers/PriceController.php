<?php

namespace App\Http\Controllers;

use App\Services\PricingService;
use Illuminate\Http\Request;

class PriceController extends Controller
{
    public function compute(Request $r, PricingService $svc)
    {
        // Örnek product payload (gerçekte DB/XML’den dolduracağız)
        $p = [
            'stock_code'        => $r->input('stockCode', 'P11743S4450'),
            'name'              => $r->input('name', 'Örnek Ürün'),
            'buy_price_vat'     => (float)$r->input('buyPriceVat', 200), // KDV dahil alış
            'commission_rate'   => (float)$r->input('commissionRate', 18),
            'volumetric_weight' => (float)$r->input('volumetricWeight', 0),
            'width'             => (float)$r->input('width', 15),
            'length'            => (float)$r->input('length', 15),
            'height'            => (float)$r->input('height', 2),
        ];

        $opt = [
            'free_ship_threshold' => (float)$r->input('freeShipThreshold', 300),
            'profit_mode'         => $r->input('profit.mode', 'percent'),
            'profit_value'        => (float)$r->input('profit.value', 15),
            'shipping'            => $this->arasTable(),
        ];

        return response()->json($svc->compute($p, $opt));
    }

public function products()
{
    return response()->json([
        ['stockCode' => 'P11743S4450', 'name' => 'Ürün 1', 'price' => 100],
        ['stockCode' => 'P11856S4460', 'name' => 'Ürün 2', 'price' => 150],
    ]);
}

public function compute(Request $request)
{
    $data = $request->validate([
        'stockCode' => 'required|string',
        'buyPriceVat' => 'required|numeric',
        'commissionRate' => 'required|numeric',
        'width' => 'required|numeric',
        'length' => 'required|numeric',
        'height' => 'required|numeric',
        'profit.mode' => 'required|string',
        'profit.value' => 'required|numeric',
        'freeShipThreshold' => 'required|numeric',
    ]);

    // Hesaplama örneği
    $price = $data['buyPriceVat'] + ($data['buyPriceVat'] * $data['commissionRate'] / 100);
    if ($data['profit']['mode'] === 'percent') {
        $price += ($price * $data['profit']['value'] / 100);
    } else {
        $price += $data['profit']['value'];
    }

    return response()->json([
        'stockCode' => $data['stockCode'],
        'calculatedPrice' => round($price, 2),
    ]);
}

}
