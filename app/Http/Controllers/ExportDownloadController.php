<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    
    public function show(Request $request, string $basename): StreamedResponse
    {
        // 1) En basit eşleme: exports klasöründe her profile altında arama
        // (Projendeki gerçek path'e göre düzenleyebilirsin)
        // Örn. daha önce path "exports/1/20250812_161733.xml" idi:
        $candidates = [
            "exports/{$basename}.xml",
            "exports/1/{$basename}.xml",  // en yaygın senaryon
            // gerekiyorsa farklı profile id'leri de ekleyebilirsin
        ];

        $disk = config('filesystems.default'); // 's3'

        $path = null;
        foreach ($candidates as $p) {
            if (Storage::disk($disk)->exists($p)) {
                $path = $p;
                break;
            }
        }

        if (!$path) {
            // İleri seviye: ExportRun tablosundan da arayabilirsin (basename'e göre)
            // $path = optional(\App\Models\ExportRun::where('path', 'like', "%/{$basename}.xml")->first())->path;
            // if (!$path || !Storage::disk($disk)->exists($path)) abort(404);

            abort(404);
        }

        $mime = 'application/xml; charset=UTF-8';
        $size = null;
        try {
            $size = Storage::disk($disk)->size($path);
        } catch (\Throwable $e) { /* boşver */ }

        $stream = Storage::disk($disk)->readStream($path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, array_filter([
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="'.$basename.'.xml"',
            // CDN/Browser cache:
            'Cache-Control'       => 'public, max-age=31536000, immutable',
            // İçeriğin sabit olduğunu varsayıyoruz; boyutu biliyorsak ekleyelim
            $size ? 'Content-Length' : null => $size,
        ], fn($v, $k) => !is_null($k) && !is_null($v), ARRAY_FILTER_USE_BOTH));
    }
}
