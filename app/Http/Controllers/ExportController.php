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
     * Public token ile XML göster – artık is_public kontrolü YOK.
     * Token doğruysa herkes erişir.
     */
    public function publicShow(string $token)
    {
        $run = ExportRun::query()
            ->where('publish_token', $token)
            ->firstOrFail(); // is_public filtresi kaldırıldı

        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        if (!$run->storage_path || !Storage::disk($disk)->exists($run->storage_path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        $content = Storage::disk($disk)->get($run->storage_path);

        return Response::make($content, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=60',
            'Content-Disposition' => 'inline; filename="' .
                (($run->name ? Str::slug($run->name) : $run->publish_token) . '.xml') . '"',
        ]);
    }

    /**
     * Admin’den imzalı link ile indir (opsiyonel; bırakıyoruz).
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
        ]);
    }
}
