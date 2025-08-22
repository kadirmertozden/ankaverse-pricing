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

    /** FileUpload değeri (dehydrate=false olduğu için elle yakalayacağız) */
    private ?string $uploadedTmpPath = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // FileUpload state'ini kaydet (Form resetlenmeden önce!)
        $state   = $this->form->getRawState();
        $tmp     = $state['xml_upload'] ?? null;
        $this->uploadedTmpPath = is_array($tmp) ? ($tmp[0] ?? null) : $tmp;

        // Profil: aktif ilk veya ilk profil
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

        // Modelde sütun olmadığı için
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
                // Yardımcı mevcutsa kullan, yoksa minimum temizlik
                if (method_exists(ExportRunResource::class, 'makeWellFormed')) {
                    $xml = ExportRunResource::makeWellFormed($raw);
                } else {
                    $xml = trim($raw) !== '' ? trim($raw) : '<?xml version="1.0" encoding="UTF-8"?><Products/>';
                }
            } else {
                // Her durumda dosya oluşsun
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

            if ($this->uploadedTmpPath) {
                try { Storage::disk($disk)->delete($this->uploadedTmpPath); } catch (\Throwable $e) {}
            }

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
