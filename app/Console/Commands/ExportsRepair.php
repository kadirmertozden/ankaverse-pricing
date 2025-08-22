<?php

namespace App\Console\Commands;

use App\Models\ExportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportsRepair extends Command
{
    protected $signature = 'exports:repair {id?}';
    protected $description = 'ExportRun.storage_path değerini mevcut dosyaları tarayarak otomatik düzeltir.';

    public function handle(): int
    {
        $id   = $this->argument('id');
        $runs = $id ? ExportRun::whereKey($id)->get() : ExportRun::get();

        $fixed = 0;
        foreach ($runs as $run) {
            $disk = $run->storage_disk ?? config('filesystems.default', 'public');
            $path = $this->find($run, $disk);
            if ($path && $path !== $run->storage_path) {
                $run->storage_path = $path;
                $run->save();
                $this->line("#{$run->id} -> {$path}");
                $fixed++;
            }
        }

        $this->info("Tamamlandı. Düzeltildi: {$fixed}");
        return self::SUCCESS;
    }

    private function find(ExportRun $run, string $disk): ?string
    {
        $cands = [];
        if ($run->storage_path) $cands[] = $run->storage_path;
        $cands[] = "exports/{$run->id}/feed.xml";
        if ($run->export_profile_id) $cands[] = $this->latestXmlIn("exports/{$run->export_profile_id}", $disk);
        $cands[] = $this->latestXmlIn("exports/{$run->id}", $disk);

        foreach ($cands as $p) {
            if ($p && Storage::disk($disk)->exists($p)) return $p;
        }
        return null;
    }

    private function latestXmlIn(string $dir, string $disk): ?string
    {
        if (!Storage::disk($disk)->exists($dir)) return null;
        $files = Storage::disk($disk)->allFiles($dir);
        $xmls  = array_values(array_filter($files, fn($p) => str_ends_with(strtolower($p), '.xml')));
        if (!$xmls) return null;
        usort($xmls, fn($a,$b) => Storage::disk($disk)->lastModified($b) <=> Storage::disk($disk)->lastModified($a));
        return $xmls[0] ?? null;
    }
}
