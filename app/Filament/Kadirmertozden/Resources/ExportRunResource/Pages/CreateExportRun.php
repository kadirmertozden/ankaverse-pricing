<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use App\Models\ExportProfile; // <-- EKLENDİ
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    private ?string $uploadedTmpPath = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // FileUpload yolunu gizli alandan al
        $this->uploadedTmpPath = isset($data['xml_tmp']) ? (string) $data['xml_tmp'] : null;
        unset($data['xml_tmp'], $data['xml_upload']); // modele yazma

        // ZORUNLU: export_profile_id ver
        $profile = ExportProfile::query()->where('is_active', true)->first()
                 ?: ExportProfile::query()->first();

        if (! $profile) {
            // Kullanıcıya net uyarı verelim, DB hatasına düşmesin
            throw new \RuntimeException('ExportProfile bulunamadı. Lütfen önce bir Export Profile oluşturun (admin/export-profiles).');
        }

        $data['export_profile_id'] = $profile->id;

        // Token
        $data['publish_token'] = $data['publish_token'] ?? $this->generateUniqueToken();

        // Varsayılanlar
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ExportRun $record */
        $record = $this->record;
        $disk   = 'public';
        $dest   = 'exports/' . $record->publish_token . '.xml';

        try {
            // Geçici dosyayı public diskten oku
            if ($this->uploadedTmpPath && Storage::disk($disk)->exists($this->uploadedTmpPath)) {
                $raw = Storage::disk($disk)->get($this->uploadedTmpPath);
                $xml = ExportRunResource::makeWellFormed($raw);
            } else {
                // Yükleme gelmediyse dahi boş şablon yaz (404 olmasın)
                $xml = '<?xml version="1.0" encoding="UTF-8"?><Products/>';
            }

            Storage::disk($disk)->put($dest, $xml);

            $record->storage_path  = $dest;
            $record->product_count = ExportRunResource::robustCountProducts($xml);
            $record->save();

            // tmp temizliği
            if ($this->uploadedTmpPath) {
                try { Storage::disk($disk)->delete($this->uploadedTmpPath); } catch (\Throwable $e) {}
            }

            Notification::make()->title('XML yüklendi')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('XML işlenemedi')->body($e->getMessage())->danger()->send();
        }
    }

    private function generateUniqueToken(int $len = 26): string
    {
        do { $token = Str::upper(Str::random($len)); }
        while (ExportRun::where('publish_token', $token)->exists());
        return $token;
    }
}
