<?php

namespace App\Services\Pricing;

use App\Models\ExportProfile;
use App\Models\Product;
use App\Models\CategoryMapping;
use App\Models\CommissionRule;
use App\Models\ShippingRule;

class PriceCalculator
{
    public function __construct(protected ExportProfile $profile) {}

    public function for(Product $p): array
    {
        $mpId = $this->profile->marketplace_id;

        // Kategori eşleme
        $categoryId = CategoryMapping::where('marketplace_id', $mpId)
            ->where('internal_category_path', $p->category_path ?? '')
            ->value('marketplace_category_id');

        // Komisyon (kural > profil > ürün)
        $commission = CommissionRule::where('marketplace_id', $mpId)
            ->where('marketplace_category_id', $categoryId)
            ->value('commission_percent');

        if ($commission === null) {
            $commission = $this->profile->commission_percent ?: (float)($p->commission_rate ?? 0);
        }

        // Kargo (desi/weight’e göre basit örnek)
        $shipping = $this->shippingCost($mpId, $p);

        // Basit hesap (marj → kargo → komisyon), gerekirse net satıştan komisyon modeline çevrilebilir
        $sell = (float) $p->buy_price_vat;
        $sell += $sell * ((float)$this->profile->min_margin / 100); // marj
        $sell += $shipping;                                         // kargo
        $sell += $sell * ((float)$commission / 100);                // komisyon

        $sell = $this->applyRounding($sell);

        return [
            'sell_price' => round($sell, 2),
            'commission_percent' => (float)$commission,
            'shipping' => round($shipping, 2),
            'marketplace_category_id' => $categoryId,
        ];
    }

    protected function shippingCost(int $mpId, Product $p): float
    {
        $desi = null;
        if (!empty($p->dims)) {
            $d = is_array($p->dims) ? $p->dims : json_decode($p->dims, true);
            if (isset($d['width'],$d['length'],$d['height'])) {
                $desi = ($d['width'] * $d['length'] * $d['height']) / 3000;
            }
        }

        $q = ShippingRule::query()->where('marketplace_id', $mpId);
        if ($desi !== null) {
            $q->where(function($x) use($desi){ $x->whereNull('desi_min')->orWhere('desi_min','<=',$desi); })
              ->where(function($x) use($desi){ $x->whereNull('desi_max')->orWhere('desi_max','>=',$desi); });
        }

        return (float)($q->value('price') ?? 0);
    }

    protected function applyRounding(float $price): float
    {
        if ($this->profile->rounding !== null) {
            return floor($price) + (float)$this->profile->rounding; // 123 → 123.99
        }
        return round($price, 2);
    }
}
