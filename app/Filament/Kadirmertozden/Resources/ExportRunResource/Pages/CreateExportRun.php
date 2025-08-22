<?php

namespace App\Filament\Resources\ExportRunResource\Pages;

use App\Filament\Resources\ExportRunResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Token zorunlu; yoksa üret
        if (empty($data['publish_token'])) {
            $data['publish_token'] = Str::upper(Str::random(26));
        }

        // Public URL'yi token'dan üret
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        // Varsayılanlar
        $data['status'] = $data['status'] ?? 'pending';
        $data['is_public'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        // Form state içinden yüklenen dosyanın yolu
        $state = $this->form->getRawState();
        $tmpPath = $state['xml_upload'] ?? null; // örn: export_tmp/abc.xml

        if ($tmpPath) {
            // XML içeriğini oku
            $xml = Storage::disk($disk)->get($tmpPath);

            // İlk storage_path yoksa ver
            if (!$record->storage_path) {
                $record->storage_path = 'exports/' . $record->id . '/feed.xml';
            }

            // Dosyayı kalıcı yerine yaz
            Storage::disk($disk)->put($record->storage_path, $xml);

            // Ürün sayısı
            $record->product_count = ExportRunResource::countProducts($xml);

            // Temizle ve kaydet
            try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
            $record->status = 'done';
            $record->published_at = now();
            $record->save();
        }
    }
}
