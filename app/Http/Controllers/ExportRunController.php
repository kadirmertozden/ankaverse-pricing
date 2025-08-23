<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ExportRunController extends Controller
{
    // Public: herkes erişebilsin
    public function show(string $token)
    {
        $run = ExportRun::where('publish_token', $token)->first();

        if (!$run || !$run->storageExists()) {
            abort(404, 'XML not found');
        }

        $xml = $run->readXmlOrNull();
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    // Public download
    public function download(string $token)
    {
        $run = ExportRun::where('publish_token', $token)->first();

        if (!$run || !$run->storageExists()) {
            abort(404, 'XML not found');
        }

        $path = $run->storage_path;
        return Storage::disk($run->storageDisk())->download($path, basename($path));
    }
}
