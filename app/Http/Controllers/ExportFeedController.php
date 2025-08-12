<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ExportFeedController extends Controller
{
    /**
     * Public feed: /feeds/{token}.xml
     */
    public function show(string $token)
    {
        $run = ExportRun::query()
            ->where('publish_token', $token)
            ->public() // scope: is_public = 1
            ->first();

        if (! $run) {
            Log::warning('Feed not found: no run', ['token' => $token]);
            abort(404);
        }

        if (blank($run->path) || Storage::disk('local')->missing($run->path)) {
            Log::warning('Feed file not found', ['token' => $token, 'path' => $run->path]);
            abort(404);
        }

        $content = Storage::disk('local')->get($run->path);

        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * İsteğe bağlı: indir (admin tarafında buton için)
     */
    public function download(ExportRun $exportRun)
    {
        if (blank($exportRun->path) || Storage::disk('local')->missing($exportRun->path)) {
            abort(404, 'Dosya bulunamadı');
        }

        $name = basename($exportRun->path);

        return response()->streamDownload(function () use ($exportRun) {
            echo Storage::disk('local')->get($exportRun->path);
        }, $name, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
