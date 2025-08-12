<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BuildPrices extends Command
{
    protected $signature = 'price:build 
        {--profile_id= : PricingProfile ID (zorunlu)}
        {--dry-run : Sadece etkilenecek kayıt sayısını göster}
        {--chunk=2000 : (MySQL dışı) fallback hesaplamada parça büyüklüğü}';

    protected $description = 'Fiyat profiline göre products.sell_price hesaplar (tek SQL UPDATE; MySQL dışı fallback).';

    public function handle(): int
    {
        try {
            $profileId = (int) ($this->option('profile_id') ?? 0);
            if ($profileId <= 0) {
                $this->error('Profil bulunamadı (--profile_id zorunlu).');
                return self::INVALID;
            }

            $profile = DB::table('pricing_profiles')->where('id', $profileId)->first();
            if (!$profile) {
                $this->error("Profil (#{$profileId}) bulunamadı.");
                return self::INVALID;
            }

            // DRY-RUN: sadece kaç ürün etkilenecek göster
            if ($this->option('dry-run')) {
                $count = DB::table('products')->where('buy_price_vat', '>', 0)->count();
                $this->output->progressStart($count);
                $this->output->progressAdvance($count);
                $this->output->progressFinish();
                $this->info("[DRY-RUN] Toplam güncellendi: {$count}");
                return self::SUCCESS;
            }

            // Sürücü tespiti
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                $affected = $this->bulkUpdateMysql($profileId);
                $this->info("Toplam güncellendi: {$affected}");
                return self::SUCCESS;
            }

            // MySQL değilse fallback
            $affected = $this->fallbackPhpChunk($profile, (int) $this->option('chunk'));
            $this->info("Toplam güncellendi (fallback): {$affected}");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('price:build hata: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Tek bir UPDATE + JOIN ile toplu fiyat güncelle (MySQL).
     */
    protected function bulkUpdateMysql(int $profileId): int
    {
        $sql = "
            UPDATE products p
            JOIN pricing_profiles pr ON pr.id = :profileId
            SET
                p.sell_price = (
                    CASE
                        WHEN pr.rounding IS NULL THEN
                            ROUND(
                                (
                                    p.buy_price_vat
                                    * (1 + COALESCE(NULLIF(p.commission_rate, 0), pr.commission_percent)/100)
                                    * (1 + COALESCE(NULLIF(p.vat_rate, 0), pr.vat_percent)/100)
                                    * (1 + pr.min_margin/100)
                                ), 2
                            )
                        ELSE
                            (
                                CASE
                                    WHEN FLOOR(
                                        p.buy_price_vat
                                        * (1 + COALESCE(NULLIF(p.commission_rate, 0), pr.commission_percent)/100)
                                        * (1 + COALESCE(NULLIF(p.vat_rate, 0), pr.vat_percent)/100)
                                        * (1 + pr.min_margin/100)
                                    ) + pr.rounding
                                    <
                                    (
                                        p.buy_price_vat
                                        * (1 + COALESCE(NULLIF(p.commission_rate, 0), pr.commission_percent)/100)
                                        * (1 + COALESCE(NULLIF(p.vat_rate, 0), pr.vat_percent)/100)
                                        * (1 + pr.min_margin/100)
                                    )
                                    THEN
                                        FLOOR(
                                            p.buy_price_vat
                                            * (1 + COALESCE(NULLIF(p.commission_rate, 0), pr.commission_percent)/100)
                                            * (1 + COALESCE(NULLIF(p.vat_rate, 0), pr.vat_percent)/100)
                                            * (1 + pr.min_margin/100)
                                        ) + 1 + pr.rounding
                                    ELSE
                                        FLOOR(
                                            p.buy_price_vat
                                            * (1 + COALESCE(NULLIF(p.commission_rate, 0), pr.commission_percent)/100)
                                            * (1 + COALESCE(NULLIF(p.vat_rate, 0), pr.vat_percent)/100)
                                            * (1 + pr.min_margin/100)
                                        ) + pr.rounding
                                END
                            )
                    END
                ),
                p.updated_at = NOW()
            WHERE
                p.buy_price_vat > 0
        ";

        return DB::update($sql, ['profileId' => $profileId]);
    }

    /**
     * MySQL dışı sürücüler için: chunk + toplu upsert fallback.
     */
    protected function fallbackPhpChunk(object $profile, int $chunk = 2000): int
    {
        $updatedTotal = 0;

        DB::table('products')
            ->where('buy_price_vat', '>', 0)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($profile, &$updatedTotal) {
                $updates = [];
                $now = now();

                foreach ($rows as $p) {
                    $cost = (float) ($p->buy_price_vat ?? 0);
                    if ($cost <= 0) {
                        continue;
                    }

                    $commission = is_numeric($p->commission_rate) ? (float)$p->commission_rate : (float)$profile->commission_percent;
                    $vat        = is_numeric($p->vat_rate)       ? (float)$p->vat_rate       : (float)$profile->vat_percent;
                    $minMargin  = (float)$profile->min_margin;
                    $rounding   = is_numeric($profile->rounding) ? (float)$profile->rounding : null;

                    $calc = $cost;
                    $calc *= (1 + $commission/100);
                    $calc *= (1 + $vat/100);
                    $calc *= (1 + $minMargin/100);

                    if ($rounding !== null) {
                        $floor = floor($calc);
                        $candidate = $floor + $rounding;     // .99 gibi
                        $price = ($candidate < $calc) ? ($floor + 1 + $rounding) : $candidate;
                    } else {
                        $price = round($calc, 2);
                    }

                    $updates[] = [
                        'id'         => $p->id,
                        'sell_price' => round($price, 2),
                        'updated_at' => $now,
                    ];
                }

                if (!empty($updates)) {
                    DB::table('products')->upsert($updates, ['id'], ['sell_price', 'updated_at']);
                    $updatedTotal += count($updates);
                }
            });

        return $updatedTotal;
    }
}
