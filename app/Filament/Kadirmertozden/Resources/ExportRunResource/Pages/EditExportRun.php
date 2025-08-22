<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // publish_token değişmemeli; yine de public URL'i senkronla
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        if (!empty($this->record->publish_token)) {
            $data['publish_token'] = $this->record->publish_token;
        }
        $data['path'] = $base . '/' . $data['publish_token'];

        unset($data['xml_upload']);

        // export_profile_id mevcut kayıttan kalsın
        if (!empty($this->record->export_profile_id)) {
            $data['export_profile_id'] = $this->record->export_profile_id;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Edit ekranında XML tekrar yüklenmişse, üzerine yaz (opsiyonel)
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        $state   = $this->form->getRawState();
        $tmpPath = $state['xml_upload'] ?? null;

        if ($tmpPath) {
            $xml = Storage::disk($disk)->get($tmpPath);

            if (!$record->storage_path) {
                $record->storage_path = 'exports/' . $record->id . '/feed.xml';
            }

            Storage::disk($disk)->put($record->storage_path, $xml);
            $record->product_count = ExportRunResource::countProducts($xml);
            $record->published_at  = now();
            $record->status        = 'done';
            $record->save();

            try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
        }
    }
}
