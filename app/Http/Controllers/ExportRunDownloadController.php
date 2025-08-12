<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;

class ExportDownloadController extends Controller
{
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
