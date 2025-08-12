<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    // /exports/t/{token}
    public function showByToken(Request $request, string $token)
    {
        Log::info('ENTER showByToken', ['token' => $token]);

        $run = ExportRun::where('publish_token', $token)->firstOrFail();

        if (!$run->is_public) {
            abort(404, 'Dosya yayında değil');
        }

        return $this->serve($run->path);
    }

    // /exports/{folder}/{any}
    public function showByPath(Request $request, string $folder, string $any)
    {
        $path = "exports/{$folder}/{$any}";
        Log::info('ENTER showByPath', ['path' => $path]);

        // İstersen bu DB doğrulamayı kaldırabilirsin
        $isPublic = ExportRun::where('path', $path)->where('is_public', 1)->exists();
        if (!$isPublic) {
            abort(404, 'Dosya yayında değil');
        }

        return $this->serve($path);
    }

    /** exports diskinden stream eder */
    protected function serve(string $path): StreamedResponse
    {
        $disk = Storage::disk('exports');

        if (!$disk->exists($path)) {
            Log::warning('Feed file not found (exports disk)', ['path' => $path]);
            abort(404, 'XML dosyası bulunamadı');
        }

        $lastModified = $disk->lastModified($path);
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
            'Last-Modified'       => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'Cache-Control'       => 'public, max-age=300, s-maxage=300',
            'ETag'                => sha1($path.$lastModified.$size),
        ]);
    }
}
