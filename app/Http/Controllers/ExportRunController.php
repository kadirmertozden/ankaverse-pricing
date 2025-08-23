<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ExportRunController extends Controller
{
    public function show(string $token)
    {
        $path = "exports/{$token}.xml";

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $xml = Storage::disk('public')->get($path);

        return Response::make($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function download(string $token)
    {
        $path = "exports/{$token}.xml";

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->download($path, "{$token}.xml", [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
