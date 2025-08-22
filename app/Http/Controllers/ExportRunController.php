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
        $record = ExportRun::where('publish_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $disk = Storage::disk('public');
        if (!$record->storage_path || !$disk->exists($record->storage_path)) {
            abort(404);
        }

        $content = $disk->get($record->storage_path);

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
        if (!$record->storage_path || !$disk->exists($record->storage_path)) {
            abort(404);
        }

        $filename = $record->publish_token . '.xml';
        return Response::streamDownload(function () use ($disk, $record) {
            echo $disk->get($record->storage_path);
        }, $filename, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
