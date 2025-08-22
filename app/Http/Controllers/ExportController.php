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
     * Public token ile XML göster – HERKESE AÇIK.
     * Her zaman sadece ilgili kaydın kendi storage_path'i kullanılır.
     * Dosya yoksa 404; başka kaydın dosyasına ASLA yönlendirme/otomatik tamir yapılmaz.
     */
    public function publicShow(string $token)
    {
        $run = ExportRun::query()
            ->where('publish_token', $token)
            ->firstOrFail();

        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        if (!$run->storage_path || !Storage::disk($disk)->exists($run->storage_path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        $raw = Storage::disk($disk)->get($run->storage_path);

        // Başındaki BOM/çöp karakterleri at (çıktıyı bozmamak için hafif sanitize)
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $raw ?? '');
        $xml = ltrim($xml);
        $pos = strpos($xml, '<');
        if ($pos !== false && $pos > 0) {
            $xml = substr($xml, $pos);
        }

        return Response::make($xml, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            // Ara katman/NGINX/CDN cache karışıklığı yaşamamak için:
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
            'Content-Disposition' => 'inline; filename="' .
                (($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml') . '"',
        ]);
    }

    /**
     * Admin’den imzalı link ile indir.
     */
    public function adminDownload(Request $request, ExportRun $run)
    {
        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        if (!$run->storage_path || !Storage::disk($disk)->exists($run->storage_path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        $filename = ($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml';

        return Storage::disk($disk)->download($run->storage_path, $filename, [
            'Content-Type' => 'application/xml; charset=utf-8',
            // İndirmenin cache edilmesinde sakınca yok; bırakıyoruz.
        ]);
    }
}
