<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    /**
     * Örnek route: GET /exports/{basename}
     * basename: "01K37R3JPQ98TC8GN19RG42ZRX" (uzantısız token)
     */
    public function show(Request $request, string $basename): StreamedResponse
    {
        $filename = $basename . '.xml';
        $path = "exports/{$filename}"; // public disk

        if (Storage::disk('public')->missing($path)) {
            abort(404, 'Dosya bulunamadı');
        }

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('public')->get($path);
        }, $filename, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
