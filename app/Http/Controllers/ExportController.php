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
    /**
     * Token ile yayın: /exports/t/{token}
     * ExportRun.publish_token ile eşleşir ve is_public=1 ise dosyayı döner.
     */
    public function showByToken(Request $request, string $token): StreamedResponse
    {
        Log::info('ENTER showByToken', ['token' => $token]);

        /** @var ExportRun|null $run */
        $run = ExportRun::where('publish_token', $token)->firstOrFail();

        if (!$run->is_public) {
            abort(404, 'Dosya yayında değil');
        }

        // DB'deki path'i kullan (ör: "exports/1/foo.xml")
        [$disk, $resolvedPath] = $this->resolveExportsPath($run->path);

        return $this->streamXml($disk, $resolvedPath);
    }

    /**
     * Yol bazlı erişim:
     *  - /exports/1/20250812_161733.xml
     *  - /exports/1/manual/manual-20250812-170641.xml
     *
     * NOT: Route tanımı {any} ile olmalı ve where('any','.*') eklenmeli.
     */
    public function showByPath(Request $request, string $folder, string $any): StreamedResponse
    {
		Log::info('ENTER showByPath', ['folder'=>$folder, 'any'=>$any]);
$incoming = "exports/{$folder}/{$any}";
$normalized = "1/{$any}";

$existsDisk = Storage::disk('exports')->exists($normalized);
$existsDb   = \App\Models\ExportRun::whereIn('path', [$normalized, $incoming])
               ->where('is_public',1)->exists();

Log::info('EXPORT DIAG', [
  'incoming'=>$incoming,
  'normalized'=>$normalized,
  'disk_exists'=>$existsDisk,
  'db_exists'=>$existsDb,
]);
        $incoming = "exports/{$folder}/{$any}";
$normalized = "1/{$any}";

$isPublic = \App\Models\ExportRun::whereIn('path', [$normalized, $incoming])->where('is_public',1)->exists();
// if (!$isPublic) { abort(404, 'Dosya yayında değil'); }


        [$disk, $resolvedPath] = $this->resolveExportsPath($incoming);

        return $this->streamXml($disk, $resolvedPath);
    }

    /**
     * Exports diskinde gelen path'i normalize eder.
     * Aşağıdaki olasılıkları sırasıyla dener:
     *   - "1/..." (kökte arar)
     *   - "exports/1/..." (köke "exports/" ön eki ile arar)
     *   - Eğer kök zaten ".../private/exports" ise, gelen "exports/..." başını kırpar.
     *
     * @return array{0:\Illuminate\Contracts\Filesystem\Filesystem,1:string} [$disk, $chosenPath]
     */
    protected function resolveExportsPath(string $path): array
    {
        // $resolvedPath bulunduğunda:
$disk = Storage::disk('exports');

// private bucket => geçici imzalı URL (10 dk)
$url = $disk->temporaryUrl($resolvedPath, now()->addMinutes(10));

return redirect()->away($url, 302);


        $root = (string) config('filesystems.disks.exports.root'); // bilgi amaçlı
        $clean = ltrim($path, '/');

        // Aday listesi (tekrarları at)
        $candidates = array_values(array_unique([
            // Eğer kök ".../private/exports" ise "exports/" ön ekini kırp
            preg_replace('#^exports/#', '', $clean),
            // Düz gelen
            $clean,
            // Ters olasılık: kök ".../private" ise "exports/" ile birlikte dene
            'exports/' . $clean,
        ]));

        foreach ($candidates as $try) {
            if ($disk->exists($try)) {
                Log::info('RESOLVED exports path', ['root' => $root, 'chosen' => $try]);
                return [$disk, $try];
            }
        }

        Log::warning('Feed file not found (exports disk)', ['tried' => $candidates, 'root' => $root]);
        abort(404, 'XML dosyası bulunamadı');
    }

    /**
     * XML içeriğini stream eder; Content-Type, ETag, Cache-Control vb. ile.
     */
    protected function streamXml($disk, string $path): StreamedResponse
    {
        $lastModified = $disk->lastModified($path);
        $size         = $disk->size($path);

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
            'Last-Modified'       => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
            'Cache-Control'       => 'public, max-age=300, s-maxage=300',
            'ETag'                => sha1($path . '|' . $lastModified . '|' . $size),
            'X-Exports-Path'      => $path, // debug için faydalı
        ]);
    }
}
