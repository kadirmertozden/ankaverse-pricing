<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportController extends Controller
{
    /**
     * Token'ı bilen HERKES XML'i görsün (auth yok, is_public kontrolü yok).
     * Dosya yoksa 404 döner (500 değil).
     */
    public function publicShow(string $token)
    {
        $run = ExportRun::query()
            ->where('publish_token', $token)
            ->firstOrFail();

        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        // Dosya yolunu sağlamlaştır (gerekirse otomatik bul & tamir et)
        $path = $this->findExistingXmlPath($run, $disk);
        if (!$path) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        // Otomatik tamir: kayıt üstüne yaz
        if ($run->storage_path !== $path) {
            $run->storage_path = $path;
            $run->save();
        }

        $content = Storage::disk($disk)->get($path);

        return Response::make($content, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            'Cache-Control'       => 'public, max-age=60',
            'Content-Disposition' => 'inline; filename="' .
                (($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml') . '"',
        ]);
    }

    /**
     * Admin’den imzalı link ile indir (opsiyonel).
     */
    public function adminDownload(Request $request, ExportRun $run)
    {
        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        $path = $this->findExistingXmlPath($run, $disk);
        if (!$path) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        // Otomatik tamir
        if ($run->storage_path !== $path) {
            $run->storage_path = $path;
            $run->save();
        }

        $filename = ($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml';
        return Storage::disk($disk)->download($path, $filename, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    /**
     * storage_path yoksa ya da dosya bulunamıyorsa, makul aday yolları dener:
     *  - Mevcut storage_path
     *  - exports/{run->id}/feed.xml
     *  - exports/{run->id}/ klasöründeki en yeni .xml
     *  - exports/{run->export_profile_id}/ klasöründeki en yeni .xml
     */
    private function findExistingXmlPath(ExportRun $run, string $disk): ?string
    {
        $candidates = [];

        // 1) Kayıttaki yol
        if ($run->storage_path) {
            $candidates[] = $run->storage_path;
        }

        // 2) Varsayılan yol (bizim yeni strateji)
        $candidates[] = 'exports/' . $run->id . '/feed.xml';

        // 3) Bu kayda ait klasörde en yeni .xml
        $latestInId = $this->latestXmlIn("exports/{$run->id}", $disk);
        if ($latestInId) $candidates[] = $latestInId;

        // 4) Profillere göre (eski düzeniniz bu klasör yapısını kullanıyorsa)
        if ($run->export_profile_id) {
            $latestInProfile = $this->latestXmlIn("exports/{$run->export_profile_id}", $disk);
            if ($latestInProfile) $candidates[] = $latestInProfile;
        }

        // Adayları sırayla kontrol et
        foreach ($candidates as $path) {
            if ($path && Storage::disk($disk)->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function latestXmlIn(string $dir, string $disk): ?string
    {
        if (!Storage::disk($disk)->exists($dir)) {
            return null;
        }
        $files = Storage::disk($disk)->allFiles($dir);
        $xmls  = array_values(array_filter($files, fn($p) => str_ends_with(strtolower($p), '.xml')));
        if (empty($xmls)) return null;

        usort($xmls, function ($a, $b) use ($disk) {
            return Storage::disk($disk)->lastModified($b) <=> Storage::disk($disk)->lastModified($a);
        });

        return $xmls[0] ?? null;
    }
}
