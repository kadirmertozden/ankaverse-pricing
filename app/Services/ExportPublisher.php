<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use App\Models\ExportRun;

class ExportPublisher
{
    public function upload(ExportRun $run, ?string $contents = null): bool
    {
        $disk = 's3';
        $path = $run->path;

        if ($contents === null) {
            // Eğer localde bir kopyan varsa buradan oku, yoksa sadece var mı kontrol et
            $local = storage_path('app/' . $path);
            if (is_file($local)) {
                $contents = file_get_contents($local);
            }
        }

        if ($contents !== null) {
            Storage::disk($disk)->put($path, $contents);
        }

        // Dosya R2'de var mı?
        if (!Storage::disk($disk)->exists($path)) {
            return false;
        }

        // Yayın meta güncelle
        $run->is_public = true;
        $run->published_at = now();
        $run->save();

        return true;
    }
}
