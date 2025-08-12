<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;

class ExportFeedController extends Controller
{
    // Public feed: https://.../feeds/{token}.xml
    public function show(string $token)
    {
        $run = ExportRun::where('publish_token', $token)
            ->where('is_public', true)
            ->firstOrFail();

        if (! $run->path || ! Storage::disk('local')->exists($run->path)) {
            abort(404, 'Feed file not found');
        }

        return response(Storage::disk('local')->get($run->path), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    // Admin indir
    public function download(ExportRun $exportRun)
    {
        abort_unless($exportRun->path && Storage::disk('local')->exists($exportRun->path), 404);
        return Storage::disk('local')->download($exportRun->path, basename($exportRun->path), [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
