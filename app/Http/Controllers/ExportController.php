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
     * Token ile XML göster – HERKESE AÇIK.
     * Sadece ilgili kaydın kendi storage_path'i kullanılır.
     * Dosya yoksa 404; başka kaydın dosyasına yönlendirme yapılmaz.
     */
    public function publicShow(string $token)
    {
        $run = ExportRun::query()
            ->where('publish_token', $token)
            ->firstOrFail();

        $disk = 'public'; // sabit disk

        // storage_path yoksa varsayılan kuralı uygula
        $storagePath = $run->storage_path ?: ('exports/' . $run->publish_token . '.xml');

        if (!Storage::disk($disk)->exists($storagePath)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        $raw = Storage::disk($disk)->get($storagePath);

        // Hafif sanitize: BOM/baş çöpü at
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $raw ?? '');
        $xml = ltrim($xml);
        $pos = strpos($xml, '<');
        if ($pos !== false && $pos > 0) {
            $xml = substr($xml, $pos);
        }

        return Response::make($xml, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            // CDN/Tarayıcı cache karışmasın:
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
        $disk = 'public';

        $storagePath = $run->storage_path ?: ('exports/' . $run->publish_token . '.xml');
        if (!Storage::disk($disk)->exists($storagePath)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        $filename = ($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml';

        return Storage::disk($disk)->download($storagePath, $filename, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
