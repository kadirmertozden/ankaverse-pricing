<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use App\Models\ExportProfile;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    /** FileUpload yolunu geçici tutacağız */
    private ?string $uploadedTmpPath = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // FileUpload'tan gizli alana aktarılan yolu yakala
        $this->uploadedTmpPath = isset($data['xml_tmp']) ? (string) $data['xml_tmp'] : null;
        unset($data['xml_tmp']); // modele yazma

        // Profil
        $profile = ExportProfile::query()->where('is_active', true)->first()
                 ?: ExportProfile::query()->first();
        if (!$profile) {
            throw new \RuntimeException('ExportProfile bulunamadı. Lütfen önce bir Export Profile oluşturun.');
        }
        $data['export_profile_id'] = $profile->id;

        // Token + Public URL
        $data['publish_token'] = $this->generateUniqueToken();
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        // Varsayılanlar
        $data['status']    = 'pending';
        $data['is_public'] = true;

        // xml_upload alanını modelden uzaklaştır
        unset($data['xml_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ExportRun $record */
        $record = $this->record;
        $disk   = 'public';

        $desiredPath = 'exports/' . $record->publish_token . '.xml';

        try {
            $xml = null;

            if ($this->uploadedTmpPath && Storage::disk($disk)->exists($this->uploadedTmpPath)) {
                $raw = Storage::disk($disk)->get($this->uploadedTmpPath);
                $xml = method_exists(ExportRunResource::class, 'makeWellFormed')
                    ? ExportRunResource::makeWellFormed($raw)
                    : (trim($raw) !== '' ? trim($raw) : '<?xml version="1.0" encoding="UTF-8"?><Products/>');
            } else {
                // Yükleme gelmediyse bile bir placeholder yarat
                $xml = '<?xml version="1.0" encoding="UTF-8"?><Products/>';
            }

            Storage::disk($disk)->put($desiredPath, $xml);

            $record->storage_path  = $desiredPath;
            $record->product_count = method_exists(ExportRunResource::class, 'robustCountProducts')
                ? ExportRunResource::robustCountProducts($xml)
                : 0;
            $record->status       = 'done';
            $record->published_at = now();
            $record->save();

            // Geçici dosyayı temizle
            if ($this->uploadedTmpPath) { try { Storage::disk($disk)->delete($this->uploadedTmpPath); } catch (\Throwable $e) {} }

            Notification::make()->title('Export oluşturuldu')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('XML işlenirken hata')->body($e->getMessage())->danger()->send();
        }
    }

    private function generateUniqueToken(int $len = 26): string
    {
        do { $token = Str::upper(Str::random($len)); }
        while (ExportRun::where('publish_token', $token)->exists());
        return $token;
    }
}
