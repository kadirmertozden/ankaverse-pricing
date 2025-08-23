<?php

namespace App\Console\Commands;

use App\Models\ExportRun;
use App\Services\XmlMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncExportRun extends Command
{
    protected $signature = 'exports:sync 
        {--token= : Tek bir publish_token senkronize et}
        {--source= : Alternatif kaynak URL (bu çalışmada öncelikli)}
        {--force : Kaynak/interval kontrolü atla}
        {--all : Auto-sync açık ve zamanı gelen tüm kayıtları çalıştır}';

    protected $description = 'Kaynak XML ile yayın XML\'ini (token) stok/fiyat temelli merge edip günceller.';

    public function handle(XmlMergeService $merger)
    {
        try {
            if ($this->option('all')) {
                $runs = ExportRun::query()
                    ->where('auto_sync', true)
                    ->get()
                    ->filter(fn($r) => $this->option('force') ? true : $r->dueForSync());

                if ($runs->isEmpty()) {
                    $this->info('Çalışacak kayıt yok.');
                    return self::SUCCESS;
                }

                foreach ($runs as $run) {
                    $this->syncOne($run, $merger, $this->option('source'));
                }
                return self::SUCCESS;
            }

            $token = (string)$this->option('token');
            if (!$token) {
                $this->error('--token veya --all vermelisiniz.');
                return self::INVALID;
            }

            $run = ExportRun::where('publish_token', $token)->first();
            if (!$run) {
                $this->error("ExportRun bulunamadı: {$token}");
                return self::FAILURE;
            }

            $this->syncOne($run, $merger, $this->option('source'));
            return self::SUCCESS;

        } catch (Throwable $e) {
            Log::error('exports:sync hata', ['ex' => $e]);
            $this->error("Hata: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function syncOne(ExportRun $run, XmlMergeService $merger, ?string $sourceOpt = null): void
    {
        $source = $sourceOpt ?: $run->source_url;
        if (!$source) {
            $this->warn("{$run->name} ({$run->publish_token}) için source_url boş, atlanıyor.");
            return;
        }

        $this->line("→ {$run->name} ({$run->publish_token}) çekiliyor: {$source}");

        // 1) Kaynak xml indir
        $resp = Http::timeout(60)->retry(2, 1.0)->get($source);
        if (!$resp->ok()) {
            $this->error("Kaynak indirilemedi: HTTP ".$resp->status());
            return;
        }
        $incoming = trim($resp->body());

        // 2) Mevcut xml (yayın) oku (yoksa boş Products oluştur)
        $current = $run->readXmlOrNull() ?? '';

        // 3) Merge
        $merged = $merger->merge($current, $incoming);

        // 4) Yaz
        $run->writeXml($merged);
        $run->last_synced_at = now();
        $run->save();

        $this->info("✓ Güncellendi: ".$run->publicUrl());
    }
}
