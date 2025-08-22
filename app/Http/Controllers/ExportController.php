<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportController extends Controller
{
    public function publicShow(string $token)
    {
        $run = ExportRun::query()
            ->where('publish_token', $token)
            ->firstOrFail();

        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        // Dosya bulun ve varsa oku
        if (!$run->storage_path || !Storage::disk($disk)->exists($run->storage_path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }
        $raw = Storage::disk($disk)->get($run->storage_path);

        // SANITIZE: Başındaki BOM/çöp karakterleri at
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $raw ?? '');
        $xml = ltrim($xml);
        $pos = strpos($xml, '<');
        if ($pos !== false && $pos > 0) {
            $xml = substr($xml, $pos);
        }

        return Response::make($xml, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            'Cache-Control'       => 'public, max-age=60',
            'Content-Disposition' => 'inline; filename="' .
                (($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml') . '"',
        ]);
    }

    public function adminDownload(Request $request, ExportRun $run)
    {
        $disk = $run->storage_disk ?? config('filesystems.default', 'public');
        if (!$run->storage_path || !Storage::disk($disk)->exists($run->storage_path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }
        $filename = ($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml';
        return Storage::disk($disk)->download($run->storage_path, $filename, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
