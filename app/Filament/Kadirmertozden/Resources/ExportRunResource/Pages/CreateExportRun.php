<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tmpPath = $data['upload_file'] ?? null;
        if (!$tmpPath) {
            return $data;
        }

        // Token üret
        $token = Str::upper(Str::random(26));

        // Hedef path: exports/manual/{TOKEN}.xml
        $destPath = "exports/manual/{$token}.xml";

        // Taşı
        if (Storage::disk('public')->exists($tmpPath)) {
            Storage::disk('public')->makeDirectory("exports/manual");
            Storage::disk('public')->move($tmpPath, $destPath);
        }

        $data = [
            'publish_token' => $token,
            'path'          => $destPath,
            'status'        => 'done',
            'is_public'     => true,
            'published_at'  => now(),
            'product_count' => 0,
        ];

        return $data;
    }
}
