<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PriceStats extends Command
{
    protected $signature = 'price:stats {--minutes=60 : Kaç dakikaya bakılsın}
                                      {--profile_id=1 : (opsiyonel) Profil ID}';

    protected $description = 'Fiyat/stok güncelleme istatistikleri';

    public function handle(): int
    {
        $minutes   = (int) $this->option('minutes');
        $profileId = (int) $this->option('profile_id');

        $total = DB::table('products')->count();

        $updatedRecently = DB::table('products')
            ->where('updated_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"))
            ->count();

        $sellPriceNull = DB::table('products')->whereNull('sell_price')->count();

        $last5 = DB::table('products')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['stock_code','buy_price_vat','sell_price','updated_at']);

        $profile = DB::table('pricing_profiles')->where('id', $profileId)->first();

        $this->line("");
        $this->info("=== Price Stats ===");
        $this->line("Toplam ürün               : {$total}");
        $this->line("Son {$minutes} dk güncellenen : {$updatedRecently}");
        $this->line("sell_price NULL           : {$sellPriceNull}");
        $this->line("Profil #{$profileId}      : " . ($profile ? ($profile->name ?? 'Var') : 'YOK'));
        $this->line("");
        $this->info("Son 5 kayıt:");
        foreach ($last5 as $row) {
            $this->line(
                sprintf(
                    "- %s | maliyet=%s | satış=%s | %s",
                    $row->stock_code,
                    $row->buy_price_vat,
                    $row->sell_price,
                    $row->updated_at
                )
            );
        }

        return self::SUCCESS;
    }
}
