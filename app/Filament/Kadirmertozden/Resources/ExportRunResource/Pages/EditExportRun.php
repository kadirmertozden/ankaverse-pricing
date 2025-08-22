<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // publish_token değiştiyse, dosya yolu da yeni token ile yazılacak (afterSave içinde ele alıyoruz)
        return $data;
    }

    protected function afterSave(): void
    {
        $xmlText = $this->data['xml_text'] ?? null;

        $uploadedPath = null;
        if (isset($this->data['xml_upload']) && is_array($this->data['xml_upload'])) {
            $uploadedPath = $this->data['xml_upload']['realPath'] ?? $this->data['xml_upload']['path'] ?? null;
        } elseif (is_string($this->data['xml_upload'] ?? null)) {
            $uploadedPath = $this->data['xml_upload'];
        }

        if ($uploadedPath || ($xmlText && trim($xmlText) !== '')) {
            try {
                ExportRunResource::persistXml($this->record, $xmlText, $uploadedPath);

                Notification::make()
                    ->success()
                    ->title('XML güncellendi')
                    ->body('Ürün sayısı: ' . $this->record->product_count)
                    ->send();
            } catch (\Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title('XML güncellenemedi')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}
 