<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdoptSupplier extends Command
{
    protected $signature = 'adopt:supplier
        {supplier_code : suppliers.code (örn: yenitoptanci)}
        {--only-changed : Sadece son gördüğümüzden değişenleri al}
        {--dry-run : Yazmadan sadece kaç kayıt işlenecek göster}';

    protected $description = 'supplier_products → products aktarımı (normalize / upsert, kolon-keşifli).';

    public function handle(): int
    {
        $code = (string) $this->argument('supplier_code');
        $supplier = DB::table('suppliers')->where('code', $code)->first();

        if (! $supplier) {
            $this->error("Tedarikçi bulunamadı: {$code}");
            return self::FAILURE;
        }
        $supplierId = (int) $supplier->id;

        // Ürün tablosundaki mevcut kolonları keşfet
        $cols = array_flip(Schema::getColumnListing('products'));

        $has = fn(string $c) => array_key_exists($c, $cols);

        // Konflikt anahtarı: sku varsa onu kullan, yoksa stock_code
        $conflictKey = $has('sku') ? 'sku' : ($has('stock_code') ? 'stock_code' : null);
        if (! $conflictKey) {
            $this->error("products tablosunda ne 'sku' ne de 'stock_code' var. En az birine ihtiyaç var.");
            return self::FAILURE;
        }

        $q = DB::table('supplier_products')->where('supplier_id', $supplierId);
        if ($this->option('only-changed')) {
            $q->where('last_seen_at', '>=', now()->subDay());
        }

        $total = (clone $q)->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;

        $q->orderBy('id')->chunk(500, function ($rows) use (&$processed, $bar, $supplier, $supplierId, $has, $conflictKey) {
            $upserts = [];

            foreach ($rows as $r) {
                // Boyutları toparla
                $dims = is_string($r->dims) ? json_decode($r->dims, true) : (array)($r->dims ?? []);
                $width  = (float)($dims['width']  ?? $r->width  ?? 0);
                $length = (float)($dims['length'] ?? $r->length ?? 0);
                $height = (float)($dims['height'] ?? $r->height ?? 0);
                $vol    = (float)($dims['volumetric_weight'] ?? $r->volumetric_weight ?? 1);

                // SKU üret (tabloda sku kolonu varsa mutlaka set edelim; NOT NULL olabilir)
                $sku = Str::limit(Str::slug(($supplier->code ?? 'sup').'-'.($r->stock_code ?? Str::uuid()), '-'), 60, '');

                // Tek bir satır dizisi, SADECE var olan kolonlar doldurulacak
                $row = [
                    // Kimlik/kaynak alanları
                    $has('supplier_id')         ? 'supplier_id'         : null => $supplierId,
                    $has('supplier_stock_code') ? 'supplier_stock_code' : null => $r->stock_code,

                    // Kimlikler
                    $has('sku')         ? 'sku'         : null => $sku,
                    $has('stock_code')  ? 'stock_code'  : null => (string) $r->stock_code,

                    // Ad/marka
                    $has('name')        ? 'name'        : null => $r->name ?? (string) $r->stock_code,
                    $has('brand')       ? 'brand'       : null => $r->brand ?? null,

                    // Kategori & açıklama & görseller
                    $has('category_path') ? 'category_path' : null => $r->category_path,
                    $has('description')   ? 'description'   : null => $r->description,
                    $has('images')        ? 'images'        : null => $r->images,

                    // Fiyat/komisyon/KDV/currency
                    $has('base_cost')      ? 'base_cost'      : null => $r->buy_price_vat,
                    $has('buy_price_vat')  ? 'buy_price_vat'  : null => $r->buy_price_vat,
                    $has('commission_rate')? 'commission_rate': null => $r->commission_rate ?? 0,
                    $has('vat_rate')       ? 'vat_rate'       : null => $r->vat_rate ?? 0,
                    $has('currency')       ? 'currency'       : null => $this->normalizeCurrency($r->currency),
                    $has('currency_code')  ? 'currency_code'  : null => $this->normalizeCurrency($r->currency, legacy:true),

                    // Stok
                    $has('stock')          ? 'stock'          : null => (int) ($r->stock_amount ?? 0),
                    $has('stock_amount')   ? 'stock_amount'   : null => (int) ($r->stock_amount ?? 0),

                    // Dims
                    $has('dims')             ? 'dims'             : null => json_encode([
                        'width' => $width, 'length' => $length, 'height' => $height, 'volumetric_weight' => $vol
                    ]),
                    $has('width')            ? 'width'            : null => $width,
                    $has('length')           ? 'length'           : null => $length,
                    $has('height')           ? 'height'           : null => $height,
                    $has('volumetric_weight')? 'volumetric_weight': null => $vol,

                    // Diğerleri
                    $has('gtin')        ? 'gtin'        : null => $r->gtin ?? null,
                    $has('is_active')   ? 'is_active'   : null => ($r->is_active ? 1 : 1), // yoksa aktif varsay
                    'created_at'                        => now(),
                    'updated_at'                        => now(),
                ];

                // null key'leri temizle
                $row = array_filter($row, fn($v, $k) => !is_null($k), ARRAY_FILTER_USE_BOTH);

                // Zorunlu alan güvenliği: konflikt kolon (sku/stock_code) boş kalmasın
                if ($conflictKey === 'sku' && empty($row['sku'])) {
                    $row['sku'] = $sku;
                }
                if ($conflictKey === 'stock_code' && empty($row['stock_code'])) {
                    $row['stock_code'] = (string) $r->stock_code;
                }

                $upserts[] = $row;
                $processed++;
                $bar->advance();
            }

            if (! empty($upserts) && ! $this->option('dry-run')) {
                // update kolonları: insert edilenlerden conflict anahtarı ve created_at hariç her şey
                $updateCols = array_keys($upserts[0]);
                $updateCols = array_values(array_filter($updateCols, fn($c) => $c !== $conflictKey && $c !== 'created_at'));

                DB::table('products')->upsert(
                    $upserts,
                    [$conflictKey],
                    $updateCols
                );
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(($this->option('dry-run') ? '[DRY-RUN] ' : '') . "Toplam işlendi: {$processed}");

        return self::SUCCESS;
    }

    protected function normalizeCurrency(?string $c, bool $legacy = false): string
    {
        $c = strtoupper(trim((string) $c));
        if ($c === 'TL') $c = 'TRY';
        // legacy tabloda 'currency_code' çoğu zaman 'TL' idi; sorun değil.
        return $legacy ? ($c ?: 'TL') : ($c ?: 'TRY');
    }
}
