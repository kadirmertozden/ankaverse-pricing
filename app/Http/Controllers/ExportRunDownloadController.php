<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ExportRunDownloadController extends Controller
{
    public function download(ExportRun $exportRun)
    {
        // Basit yetki kontrolü (gerekirse genişlet)
        if (!auth()->check()) {
            abort(403);
        }

        if (!$exportRun->path || !Storage::exists($exportRun->path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        $filename = basename($exportRun->path);
        return response()->streamDownload(function () use ($exportRun) {
            echo Storage::get($exportRun->path);
        }, $filename, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
