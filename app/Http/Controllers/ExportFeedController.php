<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportFeedController extends Controller
{
    // /feeds/{token}.xml  → public görüntüleme
    public function show(string $token): Response
    {
        $run = ExportRun::where('publish_token', $token)
            ->where('is_public', true)
            ->firstOrFail();

        [$disk, $path] = $this->resolvePath($run->path);

        if (! $disk) {
            // İstersen log düş
            Log::warning('Feed file not found', ['path' => $run->path]);
            abort(404);
        }

        $xml = Storage::disk($disk)->get($path);

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="feed-'.$token.'.xml"',
        ]);
    }

    // /admin/exports/{exportRun}/download → admin indir
    public function download(ExportRun $exportRun)
    {
        [$disk, $path] = $this->resolvePath($exportRun->path);

        abort_unless($disk, 404);

        return response()->streamDownload(function () use ($disk, $path) {
            echo Storage::disk($disk)->get($path);
        }, 'export-'.$exportRun->id.'.xml', [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function resolvePath(?string $path): array
    {
        if (! $path) return [null, null];

        // Önce local, sonra public dene (eski kayıtlar için esnek olsun)
        foreach (['local', 'public', config('filesystems.default')] as $disk) {
            if (! $disk) continue;

            // Olduğu gibi dene
            if (Storage::disk($disk)->exists($path)) {
                return [$disk, $path];
            }

            // public diskinde bazen 'public/' prefix’i gerekir
            if ($disk === 'public' && ! str_starts_with($path, 'public/')) {
                $try = 'public/'.$path;
                if (Storage::disk('public')->exists($try)) {
                    return ['public', $try];
                }
            }
        }

        return [null, null];
    }
}
