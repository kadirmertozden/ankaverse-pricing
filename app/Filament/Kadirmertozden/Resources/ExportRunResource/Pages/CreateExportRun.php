<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Token üret (unique, büyük harf)
        $data['publish_token'] = $this->generateUniqueToken();
        // Public URL
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];
        // Varsayılan durumlar
        $data['status'] = 'pending';
        $data['is_public'] = true;

        // Form yalnızca name içeriyor; diğer alanları biz dolduracağız.
        unset($data['xml_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ExportRun $record */
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        // Formdan geçici yükleme yolunu al
        $state  = $this->form->getRawState();
        $tmpPath = $state['xml_upload'] ?? null; // export_tmp/xxx.xml

        if ($tmpPath) {
            $xml = Storage::disk($disk)->get($tmpPath);

            if (!$record->storage_path) {
                $record->storage_path = 'exports/' . $record->id . '/feed.xml';
            }

            // Kalıcı dosyaya yaz
            Storage::disk($disk)->put($record->storage_path, $xml);

            // Say ve kaydet
            $record->product_count = ExportRunResource::countProducts($xml);
            $record->status = 'done';
            $record->published_at = now();
            $record->save();

            // tmp temizle
            try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
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
