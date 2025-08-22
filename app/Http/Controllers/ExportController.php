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
     * Public token ile XML göster (Content-Type: application/xml).
     * Sadece is_public = true olan kayıtlar.
     */
    public function publicShow(string $token)
    {
        $run = ExportRun::query()
            ->where('publish_token', $token)
            ->where('is_public', true)
            ->firstOrFail();

        $disk = $run->storage_disk ?? config('filesystems.default', 'public');

        if (!$run->storage_path || !Storage::disk($disk)->exists($run->storage_path)) {
            // Dosya yoksa 404 ver (500 değil)
            abort(404, 'XML dosyası bulunamadı.');
        }

        $content = Storage::disk($disk)->get($run->storage_path);

        return Response::make($content, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Admin’den imzalı link ile dosyayı indir.
     * (Route 'signed' middleware ile korunur)
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
