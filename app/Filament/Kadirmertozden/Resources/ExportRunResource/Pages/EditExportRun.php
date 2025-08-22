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
    protected ?string $oldToken = null;

    protected function beforeFill(): void { $this->oldToken = $this->record->publish_token; }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Token düzenleme
        $token = strtoupper(trim((string)($data['publish_token'] ?? '')));
        if ($token === '') $token = $this->generateUniqueToken();
        if (!preg_match('/^[A-Z0-9]{16,64}$/', $token)) {
            throw new \RuntimeException('Geçersiz token formatı. Sadece A–Z ve 0–9; uzunluk 16–64.');
        }
        $exists = ExportRun::where('publish_token',$token)->where('id','!=',$this->record->id)->exists();
        if ($exists) throw new \RuntimeException('Bu token zaten kullanılıyor.');

        $data['publish_token'] = $token;

        // Public URL
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE','https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        // export_profile_id kalsın
        if (!empty($this->record->export_profile_id)) $data['export_profile_id'] = $this->record->export_profile_id;

        unset($data['xml_upload']);
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $disk = 'public';

        // Token değiştiyse dosyayı da yeni isme taşı
        if ($this->oldToken && $this->oldToken !== $record->publish_token) {
            $old = 'exports/'.$this->oldToken.'.xml';
            $new = 'exports/'.$record->publish_token.'.xml';
            if (Storage::disk($disk)->exists($old)) {
                if (Storage::disk($disk)->exists($new)) Storage::disk($disk)->delete($new);
                Storage::disk($disk)->move($old,$new);
            } elseif ($record->storage_path && Storage::disk($disk)->exists($record->storage_path)) {
                Storage::disk($disk)->copy($record->storage_path,$new);
            }
            $record->storage_path = $new; $record->save();
        }

        // Edit'te yeni XML yüklenmişse yaz
        $state = $this->form->getRawState();
        $tmp = $state['xml_upload'] ?? null;
        $tmpPath = is_array($tmp) ? ($tmp[0]??null) : $tmp;

        try {
            if ($tmpPath) {
                $raw = Storage::disk($disk)->get($tmpPath);
                $xml = ExportRunResource::makeWellFormed($raw);

                $desired = 'exports/'.$record->publish_token.'.xml';
                $record->storage_path = $desired;
                Storage::disk($disk)->put($desired,$xml);

                $record->product_count = ExportRunResource::robustCountProducts($xml);
                $record->published_at = now();
                $record->status = 'done';
                $record->save();

                try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Notification::make()->title('XML işlenirken hata')->body($e->getMessage())->danger()->send();
        }
    }

    private function generateUniqueToken(int $len=26): string
    {
        do { $t = Str::upper(Str::random($len)); } while (ExportRun::where('publish_token',$t)->exists());
        return $t;
    }
}
