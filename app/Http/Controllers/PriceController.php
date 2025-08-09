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
        // Şimdilik dummy; sonra XML okuyup gerçek ürün listesi döneceğiz
        return response()->json([
            ['stock_code' => 'P11743S4450', 'name' => '4 Shot Bardaklı Matara Seti'],
        ]);
    }

    private function arasTable(): array
    {
        // 0..15 desi örnek tablo (gerçekte DB/ayar dosyasından gelecek)
        return [
            0=>66.79,1=>66.79,2=>73.48,3=>80.28,4=>88.55,
            5=>96.63,6=>103.12,7=>109.62,8=>116.10,9=>122.60,
            10=>129.10,11=>134.30,12=>139.53,13=>144.73,14=>149.94,15=>155.16,
        ];
    }
}
