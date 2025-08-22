<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportRunDownloadController extends Controller
{
    /**
     * Örnek route: GET /export-runs/{exportRun}/download
     */
    public function download(ExportRun $exportRun): StreamedResponse
    {
        // path: 'exports/1/20250812_161733.xml' gibi (public disk)
        if (blank($exportRun->path) || Storage::disk('public')->missing($exportRun->path)) {
            abort(404, 'Dosya bulunamadı');
        }

        $name = basename($exportRun->path);

        return response()->streamDownload(function () use ($exportRun) {
            echo Storage::disk('public')->get($exportRun->path);
        }, $name, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
