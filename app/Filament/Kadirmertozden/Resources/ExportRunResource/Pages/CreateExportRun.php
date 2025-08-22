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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1) Profil
        $profile = ExportProfile::query()->where('is_active', true)->first()
                 ?: ExportProfile::query()->first();

        if (!$profile) {
            throw new \RuntimeException('ExportProfile bulunamadı. Lütfen önce bir Export Profile oluşturun.');
        }

        $data['export_profile_id'] = $profile->id;

        // 2) Token & Public URL
        $data['publish_token'] = $this->generateUniqueToken();
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        // 3) Varsayılanlar
        $data['status']    = 'pending';
        $data['is_public'] = true;

        unset($data['xml_upload']); // modele gitmesin

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ExportRun $record */
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        // Upload state al (string veya array olabilir)
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

                $record->product_count = ExportRunResource::countProducts($xml);
                $record->status        = 'done';
                $record->published_at  = now();
                $record->save();

                try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            // 500 olmasın; kullanıcıya bildirim göster
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
