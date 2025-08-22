<?php

namespace App\Filament\Resources\ExportRunResource\Pages;

use App\Filament\Resources\ExportRunResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Public URL'yi publish_token’dan her kayıtta senkronla
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        return $data;
    }

    protected function afterSave(): void
    {
        // Edit ekranında tekrar dosya yüklenmişse, üzerine yaz
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        $state  = $this->form->getRawState();
        $tmpPath = $state['xml_upload'] ?? null;

        if ($tmpPath) {
            $xml = Storage::disk($disk)->get($tmpPath);

            if (!$record->storage_path) {
                $record->storage_path = 'exports/' . $record->id . '/feed.xml';
            }

            Storage::disk($disk)->put($record->storage_path, $xml);
            $record->product_count = ExportRunResource::countProducts($xml);
            $record->published_at = now();
            $record->status = 'done';
            $record->save();

            try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
        }
    }
}
