<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 1) Token düzenlemeyi kabul et
        $token = strtoupper(trim((string) ($data['publish_token'] ?? '')));

        // Boşsa otomatik üret
        if ($token === '') {
            $token = $this->generateUniqueToken();
        }

        // Format kontrolü (16–64 A–Z 0–9)
        if (!preg_match('/^[A-Z0-9]{16,64}$/', $token)) {
            throw new \RuntimeException('Geçersiz token formatı. Sadece A–Z ve 0–9; uzunluk 16–64 olmalı.');
        }

        // Benzersizlik
        $exists = ExportRun::where('publish_token', $token)
            ->where('id', '!=', $this->record->id)
            ->exists();
        if ($exists) {
            throw new \RuntimeException('Bu token zaten kullanılıyor. Lütfen farklı bir token girin.');
        }

        $data['publish_token'] = $token;

        // 2) Public URL senkron
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        // 3) export_profile_id mevcut kalsın
        if (!empty($this->record->export_profile_id)) {
            $data['export_profile_id'] = $this->record->export_profile_id;
        }

        // xml_upload modeli kirletmesin
        unset($data['xml_upload']);

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
                $raw = Storage::disk($disk)->get($tmpPath);
                $xml = ExportRunResource::sanitizeXml($raw);
                if (!ExportRunResource::isValidXml($xml)) {
                    throw new \RuntimeException('Geçersiz XML yüklendi. Kaçak & vb. karakterleri düzeltin veya CDATA kullanın.');
                }

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

    private function generateUniqueToken(int $len = 26): string
    {
        do {
            $token = Str::upper(Str::random($len));
        } while (ExportRun::where('publish_token', $token)->exists());
        return $token;
    }
}
