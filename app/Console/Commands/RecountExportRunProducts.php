<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExportRun;
use App\Services\ExportPublisher;

class RecountExportRunProducts extends Command
{
    protected $signature = 'exports:recount {id? : Tek bir ExportRun ID (boşsa tümünü sayar)}';
    protected $description = 'export_runs.product_count alanını XML dosyalarına bakarak yeniden hesaplar';

    public function handle(ExportPublisher $publisher): int
    {
        $id = $this->argument('id');

        if ($id) {
            $run = ExportRun::find($id);
            if (!$run) {
                $this->error("ExportRun #{$id} bulunamadı.");
                return self::FAILURE;
            }
            $count = $publisher->recountFromStorage($run);
            $this->info("ExportRun #{$run->id}: product_count = {$count}"); 
            return self::SUCCESS;
        }

        // Tümü
        $this->info('Tüm ExportRun kayıtları yeniden sayılıyor...');
        $bar = $this->output->createProgressBar(ExportRun::count());
        $bar->start();

        ExportRun::chunk(200, function ($chunk) use ($publisher, $bar) {
            foreach ($chunk as $run) {
                try {
                    $publisher->recountFromStorage($run);
                } catch (\Throwable $e) {
                    // Hata olsa bile devam et
                    $this->warn("  #{$run->id} hata: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Tamamlandı.');
        return self::SUCCESS;
    }
}
