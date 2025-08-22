<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Token değiştirilmesin; path senkron
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['publish_token'] = $this->record->publish_token;
        $data['path'] = $base . '/' . $data['publish_token'];

        unset($data['xml_upload']);
        // export_profile_id mevcut kalsın
        if (!empty($this->record->export_profile_id)) {
            $data['export_profile_id'] = $this->record->export_profile_id;
        }
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        $state   = $this->form->getRawState();
        $tmp     = $state['xml_upload'] ?? null;
        $tmpPath = is_array($tmp) ? ($tmp[0] ?? null) : $tmp;

        try {
            if ($tmpPath) {
                $xml = Storage::disk($disk)->get($tmpPath);

                if (!$record->storage_path) {
                    $record->storage_path = 'exports/' . $record->id . '/feed.xml';
                }

                Storage::disk($disk)->put($record->storage_path, $xml);
                $record->product_count = ExportRunResource::robustCountProducts($xml);
                $record->published_at  = now();
                $record->status        = 'done';
                $record->save();

                try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('XML işlenirken hata oluştu')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
