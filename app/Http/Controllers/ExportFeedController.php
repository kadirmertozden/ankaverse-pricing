<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportFeedController extends Controller
{
    // /feeds/{token}.xml -> public görüntüleme
    public function show(string $token): StreamedResponse
    {
        $run = ExportRun::where('publish_token', $token)
            ->where('is_public', true)
            ->firstOrFail();

        if (! $run->path || ! Storage::exists($run->path)) {
            abort(404);
        }

        return response()->stream(function () use ($run) {
            $stream = Storage::readStream($run->path);
            fpassthru($stream);
        }, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="feed-'.$run->id.'.xml"',
        ]);
    }

    // admin.exports.download -> panel içi indirme
    public function download(ExportRun $exportRun)
    {
        if (! $exportRun->path || ! Storage::exists($exportRun->path)) {
            abort(404);
        }

        return Storage::download($exportRun->path, 'feed-'.$exportRun->id.'.xml', [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
