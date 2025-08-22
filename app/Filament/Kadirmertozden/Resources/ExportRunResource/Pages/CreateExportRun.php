<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// 👇 Bunu ekledik: varsayılan profil ID'sini bulmak için
use App\Models\ExportProfile;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tmpPath = $data['upload_file'] ?? null;
        if (!$tmpPath) {
            return $data;
        }

        // 1) Varsayılan ExportProfile ID'sini bul
        // Önce aktif olanı, yoksa ilkini, o da yoksa 1'i dene
        $profileId =
            ExportProfile::where('is_active', true)->value('id')
            ?? ExportProfile::value('id')
            ?? 1;

        // 2) Yayın token'ı üret
        $token = Str::upper(Str::random(26));

        // 3) Hedef path: exports/{profile}/manual/{TOKEN}.xml (public disk)
        $destPath = "exports/{$profileId}/manual/{$token}.xml";

        // 4) Dosyayı tmp'den hedefe taşı
        if (Storage::disk('public')->exists($tmpPath)) {
            Storage::disk('public')->makeDirectory("exports/{$profileId}/manual");
            Storage::disk('public')->move($tmpPath, $destPath);
        }

        // 5) Model alanlarını doldur
        $data = [
            'export_profile_id' => $profileId,   // 👈 ZORUNLU
            'publish_token'     => $token,
            'path'              => $destPath,
            'status'            => 'done',
            'is_public'         => true,
            'published_at'      => now(),
            'product_count'     => 0,
        ];

        return $data;
    }
}
