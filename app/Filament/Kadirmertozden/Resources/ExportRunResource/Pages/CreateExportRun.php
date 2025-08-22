<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // publish_token boşsa model boot doldurur; burada dokunmuyoruz.
        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            ExportRunResource::persistXml(
                $this->record,
                $this->data['xml_text'] ?? null,
                // FileUpload storeFiles(false) old. Livewire tmp path burada olur
                isset($this->data['xml_upload']) && is_array($this->data['xml_upload'])
                    ? ($this->data['xml_upload']['realPath'] ?? $this->data['xml_upload']['path'] ?? null)
                    : (is_string($this->data['xml_upload'] ?? null) ? $this->data['xml_upload'] : null)
            );

            Notification::make()
                ->success()
                ->title('XML yüklendi')
                ->body('Yayın linki: ' . $this->record->publish_url)
                ->send();
        } catch (\Throwable $e) {
            // Kayıt oluştu ama XML yazılamadıysa: silmeyelim; kullanıcı editten düzeltebilir.
            Notification::make()
                ->danger()
                ->title('XML yüklenemedi')
                ->body($e->getMessage())
                ->send();
        }
    }
}
 