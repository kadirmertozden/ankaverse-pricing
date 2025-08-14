<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ExportRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    public function show(Request $request, string $basename): StreamedResponse
    {
        $filename = $basename . '.xml';

        $run = ExportRun::query()
            ->where('path', 'like', "%/{$filename}")
            ->latest('published_at')
            ->first();

        if (! $run) abort(404);

        $disk = config('filesystems.default', 's3');
        $path = $run->path;

        if (! Storage::disk($disk)->exists($path)) abort(404);

        $stream = Storage::disk($disk)->readStream($path);

        $asAttachment = (bool) $request->boolean('dl');

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type'              => 'application/xml; charset=UTF-8',
            'Content-Disposition'       => ($asAttachment ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
            'Cache-Control'             => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options'    => 'nosniff',
            'Content-Security-Policy'   => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'; sandbox",
            'Referrer-Policy'           => 'no-referrer',
        ]);
    }
}
