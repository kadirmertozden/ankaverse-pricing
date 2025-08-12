<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;

class ExportDownloadController extends Controller
{
    public function download(ExportRun $run)
    {
        abort_unless($run->path && Storage::disk('local')->exists($run->path), 404);

        return response()->streamDownload(function () use ($run) {
            echo Storage::disk('local')->get($run->path);
        }, 'export-'.$run->id.'.xml', [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}