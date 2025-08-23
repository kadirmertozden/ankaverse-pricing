<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function afterSave(): void
    {
        // Edit ekranında tekrar dosya yüklenmişse gizli alan gelir
        $tmp = $this->data['xml_tmp'] ?? null;
        if (! $tmp) return;

        $disk = 'public';
        try {
            if (! Storage::disk($disk)->exists($tmp)) {
                throw new \RuntimeException('Yüklenen geçici dosya bulunamadı.');
            }
            $raw = Storage::disk($disk)->get($tmp);
            $xml = ExportRunResource::makeWellFormed($raw);

            $dest = 'exports/' . $this->record->publish_token . '.xml';
            Storage::disk($disk)->put($dest, $xml);

            $this->record->storage_path  = $dest;
            $this->record->product_count = ExportRunResource::robustCountProducts($xml);
            $this->record->published_at  = now();
            $this->record->status        = 'done';
            $this->record->save();

            try { Storage::disk($disk)->delete($tmp); } catch (\Throwable $e) {}

            Notification::make()->title('XML güncellendi')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('XML güncellenemedi')->body($e->getMessage())->danger()->send();
        }
    }
}
