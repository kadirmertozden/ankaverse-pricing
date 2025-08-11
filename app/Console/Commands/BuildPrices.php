<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuildPrices extends Command
{
    protected $signature = 'price:build
        {--profile_id= : pricing_profiles.id}
        {--dry-run : Hesapla, yazma}';

    protected $description = 'Fiyat profiline göre products.sell_price hesaplar (legacy/yeni şema uyumlu).';

    public function handle(): int
    {
        $pid = (int) ($this->option('profile_id') ?: 0);
        $profile = DB::table('pricing_profiles')->find($pid);
        if (!$profile) {
            $this->error('Profil bulunamadı (--profile_id zorunlu).');
            return self::FAILURE;
        }

        $cols = Schema::getColumnListing('products');
        $has = fn($c) => in_array($c, $cols, true);

        $priceCol   = $has('sell_price') ? 'sell_price' : null;
        $costCol    = $has('base_cost') ? 'base_cost' : ($has('buy_price_vat') ? 'buy_price_vat' : null);
        $supplierCol= $has('supplier_id') ? 'supplier_id' : null;

        if (!$priceCol || !$costCol) {
            $this->error("Gerekli kolonlar yok (priceCol={$priceCol}, costCol={$costCol}).");
            return self::FAILURE;
        }

        $q = DB::table('products');
        if (!empty($profile->supplier_id) && $supplierCol) {
            $q->where($supplierCol, $profile->supplier_id);
        }

        $total = (clone $q)->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $q->orderBy('id')->chunk(500, function ($rows) use (&$updated, $bar, $costCol, $priceCol, $profile) {
            foreach ($rows as $r) {
                $price = (float) ($r->{$costCol} ?? 0);
                if ($price <= 0) { $bar->advance(); continue; }

                // Komisyon
                $price += $price * ((float)$profile->commission_percent / 100);
                // Kar
                $price += $price * ((float)$profile->min_margin / 100);
                // KDV
                $price += $price * ((float)$profile->vat_percent / 100);
                // Yuvarlama (.99 gibi)
                if (!is_null($profile->rounding)) {
                    $price = floor($price) + (float)$profile->rounding;
                }

                if (! $this->option('dry-run')) {
                    DB::table('products')->where('id', $r->id)
                        ->update([$priceCol => $price, 'updated_at' => now()]);
                }
                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(($this->option('dry-run') ? '[DRY-RUN] ' : '') . "Toplam güncellendi: {$updated}");
        return self::SUCCESS;
    }
}
