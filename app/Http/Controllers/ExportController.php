<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class ExportController extends Controller
{
    // 3a) Token bazlı erişim: /exports/t/{token}
    public function showByToken(Request $request, string $token)
    {
        /** @var ExportRun $run */
        $run = ExportRun::where('publish_token', $token)->firstOrFail();

        // Kamuya açık değilse 404 ver
        if (!$run->is_public) {
            abort(404, 'Dosya bulunamadı.');
        }

        // path örneği: 'exports/1/20250812_161733.xml'
        $path = $run->path;

        if (!Storage::disk('exports')->exists($path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        return $this->streamXml($path);
    }

    // 3b) Klasik yol: /exports/{folder}/{filename}


public function showByToken(Request $request, string $token)
{
    Log::info('ENTER showByToken', ['token' => $token]); // 👈 imza
    ...
}

public function showByPath(Request $request, string $folder, string $any)
{
    Log::info('ENTER showByPath', ['folder' => $folder, 'any' => $any]); // 👈 imza
    ...
}


    /** XML dosyasını Content-Type ve cache header’larıyla stream eder. */
    protected function streamXml(string $path): StreamedResponse
    {
        $disk = Storage::disk('exports');

        $lastModifiedTs = $disk->lastModified($path);
        $size = $disk->size($path);

        return Response::stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Content-Length'      => (string) $size,
            'Last-Modified'       => gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT',
            'Cache-Control'       => 'public, max-age=300, s-maxage=300', // 5 dk cache
            'ETag'                => sha1($path.$lastModifiedTs.$size),
        ]);
    }
}
