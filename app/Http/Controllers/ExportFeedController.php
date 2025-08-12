<?php

namespace App\Http\Controllers;

use App\Models\ExportRun;
use Illuminate\Support\Facades\Storage;

class ExportFeedController extends Controller
{
    public function show(string $token)
    {
        $run = ExportRun::where('publish_token', $token)
            ->where('is_public', true)
            ->firstOrFail();

        if (!$run->path || !Storage::exists($run->path)) {
            abort(404, 'XML dosyası bulunamadı.');
        }

        return response(Storage::get($run->path), 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
