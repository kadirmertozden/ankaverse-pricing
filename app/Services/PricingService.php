<?php

namespace App\Services;

class PricingService
{
    public function compute(array $p, array $opt): array
    {
        $free = (float)($opt['free_ship_threshold'] ?? 300);
        $profitMode = $opt['profit_mode'] ?? 'percent'; // 'percent' | 'tl'
        $profitVal  = (float)($opt['profit_value'] ?? 15);
        $rate       = (float)($p['commission_rate'] ?? 18); // %
        $alis       = (float)$p['buy_price_vat']; // KDV dahil
        $desi       = $this->desi($p);
        $shipTable  = $opt['shipping'] ?? [];
        $shipCost   = $this->aras($desi, $shipTable);

        $base = $profitMode === 'percent' ? $alis * (1 + $profitVal / 100) : $alis + $profitVal;

        $p0 = $this->price($base, 0, $rate);
        $includeShip = $p0 >= $free;
        $raw = $this->price($base, $includeShip ? $shipCost : 0, $rate);

        $price = $this->round90($raw, $free);
        $shipIncluded = $price >= $free ? $shipCost : 0.0;
        $commission = $price * $rate / 100.0;
        $net = $price - $alis - $commission - $shipIncluded;
        $margin = $price > 0 ? ($net / $price * 100.0) : 0.0;

        return [
            'sale_price'        => round($price, 2),
            'commission_rate'   => round($rate, 2),
            'commission'        => round($commission, 2),
            'shipping_included' => $shipIncluded > 0,
            'shipping_cost'     => round($shipIncluded, 2),
            'net_profit'        => round($net, 2),
            'margin_pct'        => round($margin, 2),
            'desi'              => $desi,
        ];
    }

    private function price(float $base, float $ship, float $rate): float
    {
        return ($base + $ship) / (1 - $rate / 100.0);
    }

    private function desi(array $p): int
    {
        $vol = (float)($p['volumetric_weight'] ?? 0);
        if ($vol > 0) return (int)ceil($vol);
        $w = (float)($p['width'] ?? 0);
        $l = (float)($p['length'] ?? 0);
        $h = (float)($p['height'] ?? 0);
        return $w > 0 && $l > 0 && $h > 0 ? (int)ceil(($w * $l * $h) / 3000.0) : 0;
    }

    private function aras(int $desi, array $table): float
    {
        if ($desi < 0) $desi = 0;
        if (isset($table[$desi])) return (float)$table[$desi];
        // Ekstrapolasyon
        $keys = array_keys($table);
        sort($keys);
        $last = end($keys);
        $before = prev($keys);
        $step = $table[$last] - $table[$before];
        return (float)($table[$last] + $step * ($desi - $last));
    }

    private function round90(float $price, float $thr): float
    {
        if ($price < $thr) {
            $p = floor($price) + 0.90;
            if ($p >= $thr) $p = $thr - 0.10;
            return max(0.90, round($p, 2));
        }
        $p = floor($price) + 0.90;
        if ($p < $thr) $p = $thr + 9.90;
        return round($p, 2);
    }
}
