<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['publish_token'])) {
            $data['publish_token'] = Str::upper(Str::random(26));
        }

        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        $data['path'] = $base . '/' . $data['publish_token'];

        $data['status'] = $data['status'] ?? 'pending';
        $data['is_public'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $disk   = $record->storage_disk ?? config('filesystems.default', 'public');

        $state  = $this->form->getRawState();
        $tmpPath = $state['xml_upload'] ?? null; // export_tmp/xxx.xml

        if ($tmpPath) {
            $xml = Storage::disk($disk)->get($tmpPath);

            if (!$record->storage_path) {
                $record->storage_path = 'exports/' . $record->id . '/feed.xml';
            }

            Storage::disk($disk)->put($record->storage_path, $xml);

            $record->product_count = ExportRunResource::countProducts($xml);
            $record->status = 'done';
            $record->published_at = now();
            $record->save();

            try { Storage::disk($disk)->delete($tmpPath); } catch (\Throwable $e) {}
        }
    }
}
