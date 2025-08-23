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
        // İSTENEN: Herkes erişebilsin -> is_active FİLTRESİ YOK
        $record = ExportRun::where('publish_token', $token)->firstOrFail();

        $disk = Storage::disk('public');
        $path = $record->storage_path ?: ('exports/' . $record->publish_token . '.xml');

        if (!$disk->exists($path)) {
            abort(404);
        }

        $content = $disk->get($path);

        return Response::make($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function download(string $token)
    {
        $record = ExportRun::where('publish_token', $token)->firstOrFail();

        $disk = Storage::disk('public');
        $path = $record->storage_path ?: ('exports/' . $record->publish_token . '.xml');

        if (!$disk->exists($path)) {
            abort(404);
        }

        $filename = $record->publish_token . '.xml';

        return Response::streamDownload(function () use ($disk, $path) {
            echo $disk->get($path);
        }, $filename, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
