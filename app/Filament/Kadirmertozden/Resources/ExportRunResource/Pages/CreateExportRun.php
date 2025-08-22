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
        // Zorunlu alanlar:
        $profileId = (int) ($data['export_profile_id'] ?? 0);
        $tmpPath   = $data['upload_file'] ?? null; // public disk relative path (exports/tmp/xxx.xml)

        if (!$profileId || !$tmpPath) {
            return $data;
        }

        // Yayın token’ı üret
        $token = Str::upper(Str::random(26));

        // Hedef konum: exports/{profile}/manual/{TOKEN}.xml
        $destPath = "exports/{$profileId}/manual/{$token}.xml";

        // Dosyayı tmp'den hedefe taşı
        if (Storage::disk('public')->exists($tmpPath)) {
            Storage::disk('public')->makeDirectory("exports/{$profileId}/manual");
            Storage::disk('public')->move($tmpPath, $destPath);
        }

        // Model alanlarını doldur
        $data['publish_token'] = $token;
        $data['path']          = $destPath;           // public disk
        $data['status']        = $data['status'] ?? 'done';
        $data['is_public']     = $data['is_public'] ?? true;
        $data['published_at']  = $data['published_at'] ?? now();

        // upload_file form alanını modelde tutmayacağız
        unset($data['upload_file']);

        return $data;
    }
}
