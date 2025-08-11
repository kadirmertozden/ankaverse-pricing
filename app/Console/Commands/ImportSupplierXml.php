<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportSupplierXml extends Command
{
    // ARGÜMAN + OPSİYONLAR
    protected $signature = 'import:supplier 
        {supplier_code : suppliers.code (örn: yenitoptanci)} 
        {--feed_id= : supplier_feeds.id (opsiyonel)} 
        {--url= : XML feed URL (override)} 
        {--file= : Yerel XML yolu (örn: storage/app/private/supplier.xml)} 
        {--chunk=500 : Kaç üründe bir toplu upsert yapılsın} 
        {--full : Tüm kayıtları yeniden değerlendir}';

    protected $description = 'Tedarikçi XML ürünlerini (stream) supplier_products tablosuna içe aktarır / günceller.';

    protected int $supplierId;
    protected ?int $feedId = null;

    /** Kaynak XML dosyasını indir/yerelle ve tam dosya yolunu döndür */
    protected function resolveSourcePath(): string
    {
        $dst = Storage::path('private/supplier.xml');

        // 1) --url ile override
        if ($url = $this->option('url')) {
            $this->info('XML indiriliyor: '.$url);
            $res = Http::timeout(180)->withHeaders([
                'User-Agent' => 'LaravelImporter/1.0',
                'Accept'     => 'application/xml,text/xml,*/*',
            ])->get($url);

            if (! $res->ok()) {
                throw new \RuntimeException("URL indirilemedi. HTTP ".$res->status());
            }
            Storage::put('private/supplier.xml', $res->body());
            $this->line('Kaydedildi: '.$dst);
            return $dst;
        }

        // 2) --feed_id verilmişse DB’den URL çek
        if ($this->feedId) {
            $feed = DB::table('supplier_feeds')
                ->where('id', $this->feedId)
                ->where('supplier_id', $this->supplierId)
                ->first();

            if (! $feed) {
                throw new \InvalidArgumentException("feed_id={$this->feedId} bu tedarikçide bulunamadı.");
            }
            if (! $feed->url) {
                throw new \InvalidArgumentException("feed_id={$this->feedId} için URL boş.");
            }

            $this->info('XML indiriliyor (feed_id='.$this->feedId.'): '.$feed->url);
            $res = Http::timeout(180)->withHeaders([
                'User-Agent' => 'LaravelImporter/1.0',
                'Accept'     => 'application/xml,text/xml,*/*',
            ])->get($feed->url);

            if (! $res->ok()) {
                throw new \RuntimeException("Feed URL indirilemedi. HTTP ".$res->status());
            }
            Storage::put('private/supplier.xml', $res->body());
            $this->line('Kaydedildi: '.$dst);
            return $dst;
        }

        // 3) supplier’ın ilk aktif feed’i (fallback)
        $feed = DB::table('supplier_feeds')
            ->where('supplier_id', $this->supplierId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($feed && $feed->url) {
            $this->info('XML indiriliyor (aktif feed): '.$feed->url);
            $res = Http::timeout(180)->withHeaders([
                'User-Agent' => 'LaravelImporter/1.0',
                'Accept'     => 'application/xml,text/xml,*/*',
            ])->get($feed->url);

            if (! $res->ok()) {
                throw new \RuntimeException("Aktif feed URL indirilemedi. HTTP ".$res->status());
            }
            Storage::put('private/supplier.xml', $res->body());
            $this->line('Kaydedildi: '.$dst);
            return $dst;
        }

        // 4) --file ile yerel yol
        if ($file = $this->option('file')) {
            if (! is_file($file)) {
                throw new \InvalidArgumentException("Yerel dosya bulunamadı: $file");
            }
            Storage::put('private/supplier.xml', file_get_contents($file));
            $this->line('Kaydedildi: '.$dst);
            return $dst;
        }

        // 5) Daha önce indirilen dosya varsa onu kullan
        if (! is_file($dst)) {
            throw new \InvalidArgumentException("Kaynak yok. --url / --feed_id / --file verin ya da $dst oluşturun.");
        }

        return $dst;
    }

    public function handle(): int
    {
        libxml_use_internal_errors(true);

        // supplier_code → supplier_id
        $supplierCode = (string) $this->argument('supplier_code');
        $supplier = DB::table('suppliers')->where('code', $supplierCode)->first();
        if (! $supplier) {
            $this->error("Tedarikçi bulunamadı: {$supplierCode} (suppliers.code)");
            return self::FAILURE;
        }
        $this->supplierId = (int) $supplier->id;
        $this->feedId = $this->option('feed_id') ? (int) $this->option('feed_id') : null;

        // import kaydı (opsiyonel)
        $importId = DB::table('imports')->insertGetId([
            'supplier_id' => $this->supplierId,
            'supplier_feed_id' => $this->feedId,
            'status' => 'running',
            'started_at' => now(),
            'created_by' => auth()->id() ?? null, // console’da null olur
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $path  = $this->resolveSourcePath();
            $chunk = max(1, (int) $this->option('chunk'));
        } catch (\Throwable $e) {
            $this->failImport($importId, $e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $xr = new \XMLReader();
        if (! $xr->open($path, null, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT)) {
            $this->failImport($importId, 'XML açılamadı: '.$path);
            $this->error('XML açılamadı: '.$path);
            return self::FAILURE;
        }

        $total = 0;
        $batch = [];
        $this->info("İçe aktarma başladı… (supplier: {$supplierCode})");

        while ($xr->read()) {
            if ($xr->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            // <Product> / <product>
            if (strcasecmp($xr->name, 'Product') !== 0) {
                continue;
            }

            $xmlString = $xr->readOuterXML();
            if ($xmlString === '' || $xmlString === false) {
                continue;
            }

            try {
                $node = new \SimpleXMLElement($xmlString);
            } catch (\Throwable $e) {
                continue; // bozuk node
            }

            $row = $this->mapProduct($node);
            if ($row === null) {
                continue; // zorunlu alan eksik
            }

            // supplier_id ekle
            $row['supplier_id'] = $this->supplierId;
            $batch[] = $row;
            $total++;

            if (count($batch) >= $chunk) {
                $this->upsertBatch($batch);
                $this->line("↳ yazıldı: +".count($batch));
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->upsertBatch($batch);
            $this->line("↳ yazıldı: +".count($batch));
        }

        $xr->close();

        $this->successImport($importId);
        $this->info("Bitti. Toplam: {$total}");
        return self::SUCCESS;
    }

    /** XML Product node -> supplier_products satırı */
    protected function mapProduct(\SimpleXMLElement $p): ?array
    {
        // feed alanları (gerekirse uyarlarsın)
        $stockCode   = $this->txt($p->ProductCode ?? null);
        $productName = $this->txt($p->ProductName ?? null);

        if (! $stockCode || ! $productName) {
            return null;
        }

        $category    = $this->txt($p->Category ?? null);
        $currency    = $this->txt($p->Currency ?? null);
        $descRaw     = $this->txt($p->Description ?? null, allowHtml: true);
        $price1      = $this->num($p->Price1 ?? null);
        $qty         = $this->int($p->Quantity ?? null);
        $tax         = $this->txt($p->TaxRate ?? null);
        $volume      = $this->txt($p->Volume ?? null);
        $gtin        = $this->txt($p->Gtin ?? null);
        $images      = $this->collectImages($p->Images ?? null);

        $description = $this->normalizeDescription($descRaw);
        $now = now();

        // supplier_products şeması:
        return [
            'stock_code'        => $stockCode,
            'name'              => $productName,
            'buy_price_vat'     => $price1 !== null ? number_format($price1, 2, '.', '') : '0.00',
            'vat_rate'          => $tax ?: null,
            'commission_rate'   => 0,
            'currency'          => $currency ?: 'TRY',
            'stock_amount'      => $qty,
            'category_path'     => $category ?: null,
            'images'            => $images ? json_encode($images, JSON_UNESCAPED_SLASHES) : null,
            'description'       => $description,
            'dims'              => json_encode([
                                        'width'  => '0.00',
                                        'length' => '0.00',
                                        'height' => '0.00',
                                        'volumetric_weight' => $volume ?: null,
                                    ], JSON_UNESCAPED_SLASHES),
            'raw'               => json_encode($this->simpleXmlToArray($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_seen_at'      => $now,
            'is_active'         => true,
            'updated_at'        => $now,
            'created_at'        => $now,
        ];
    }

    /** Toplu upsert → supplier_products (unique: supplier_id + stock_code) */
    protected function upsertBatch(array $rows): void
    {
        DB::table('supplier_products')->upsert(
            $rows,
            ['supplier_id', 'stock_code'], // conflict
            [
                'name','buy_price_vat','vat_rate','commission_rate','currency',
                'stock_amount','category_path','images','description','dims',
                'raw','last_seen_at','is_active','updated_at'
            ]
        );
    }

    /** Yardımcılar */
    protected function txt($val, bool $allowHtml = false): ?string
    {
        if ($val === null) return null;
        $s = (string) $val;
        if ($s === '') return null;
        return $allowHtml ? trim($s) : trim(strip_tags($s));
    }

    protected function int($val): int
    {
        $s = $this->txt($val) ?? '0';
        return (int) preg_replace('/[^\d\-]/', '', $s);
    }

    protected function num($val): ?float
    {
        $s = $this->txt($val);
        if ($s === null) return null;

        $s = str_replace([' ', "\u{00A0}"], '', $s);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d+,\d+$/', $s)) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = preg_replace('/(?<=\d)[,](?=\d{3}(\D|$))/', '', $s);
        }

        if (! is_numeric($s)) return null;
        return (float) $s;
    }

    protected function collectImages($imagesNode): array
    {
        $out = [];
        if ($imagesNode === null) return $out;

        $raw = (string) $imagesNode;
        if ($raw !== '' && strip_tags($raw) === $raw) {
            foreach (preg_split('/[;,]/', $raw) as $u) {
                $u = trim($u);
                if ($this->isUrl($u)) $out[] = $u;
            }
        }
        if ($imagesNode instanceof \SimpleXMLElement) {
            foreach ($imagesNode->children() as $img) {
                $u = trim((string) $img);
                if ($this->isUrl($u)) $out[] = $u;
            }
        }
        return array_values(array_unique($out));
    }

    protected function isUrl(string $s): bool
    {
        return $s !== '' && (bool) filter_var($s, FILTER_VALIDATE_URL);
    }

    protected function normalizeDescription(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return null;
        $clean = preg_replace("/\r\n|\r|\n/", "\n", $html);
        $clean = preg_replace('/[ \t]+/', ' ', $clean);
        return trim($clean);
    }

    protected function simpleXmlToArray(\SimpleXMLElement $xml): array
    {
        return json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true) ?? [];
    }

    protected function failImport(int $importId, string $err): void
    {
        DB::table('imports')->where('id', $importId)->update([
            'status' => 'failed',
            'error' => $err,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function successImport(int $importId): void
    {
        DB::table('imports')->where('id', $importId)->update([
            'status' => 'done',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
