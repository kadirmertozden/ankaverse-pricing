<?php

namespace App\Jobs;

use App\Models\ExportProfile;
use App\Models\ExportRun;
use App\Models\Product;
use App\Services\Export\HepsiburadaXmlBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateExportXmlJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $profileId) {}

    public function handle(): void
    {
        $profile = ExportProfile::findOrFail($this->profileId);

        $run = ExportRun::create([
            'export_profile_id' => $profile->id,
            'status' => 'running',
        ]);

        try {
            $products = Product::where('is_active', 1)->get();

            $xml = (new HepsiburadaXmlBuilder())->build($profile, $products);

            $path = "exports/{$profile->id}/" . now()->format('Ymd_His') . ".xml";
            Storage::put($path, $xml);

            $run->update([
    'status' => 'done',
    'path' => $path,
    'product_count' => $products->count(),
    'is_public' => true,
    'publish_token' => $run->publish_token ?: \Illuminate\Support\Str::random(32),
    'published_at' => now(),
]);

        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
