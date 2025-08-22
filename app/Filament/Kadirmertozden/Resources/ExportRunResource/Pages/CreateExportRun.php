<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use App\Models\ExportProfile;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1) export_profile_id'yi otomatik doldur (aktif ilk; yoksa ilk profil)
        $profile = ExportProfile::query()->where('is_active', true)->first()
                 ?: ExportProfile::query()->first();

        // Eğer hiç profil yoksa, yine de insert edelim ama DB NOT NULL ise sorun çıkar.
        // Bu durumda 500 yerine anlaşılır bir mesaj için basit bir kontrol:
        if (!$profile) {
            throw new \RuntimeException('ExportProfile bulunamadı. Lütfen önce bir Export Profile oluşturun.');
        }

        $data['export_profile_id'] = $profile->id;

        // 2) Token üret (unique, büyük harf)
        $data['publish_token'] = $this->generateUniqueToken();

        // 3) Public URL
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        // 4) Varsayılan durumlar
        $data['status']    = 'pending';
        $data['is_public'] = true;

        // Form sadece name içeriyor; xml_upload sahasını kayıta dahil etmiyoruz
        unset($data['xml_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ExportRun $record */
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        // Formdan geçici yükleme yolunu al
        $state   = $this->form->getRawState();
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
            $record->status        = 'done';
            $record->published_at  = now();
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
