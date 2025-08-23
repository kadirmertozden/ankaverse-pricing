<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    /**
     * Filament v3 imzası: handleRecordCreation(array $data): Model
     */
    protected function handleRecordCreation(array $data): Model
    {
        $run = new ExportRun([
            'name' => $data['name'] ?? 'Feed',
            'is_active' => true,
            'auto_sync' => (bool)($data['auto_sync'] ?? false),
            'source_url' => $data['source_url'] ?? null,
            'sync_interval_minutes' => (int)($data['sync_interval_minutes'] ?? 30),
        ]);
        $run->save(); // publish_token üretildi

        // FileUpload alanını farklı tiplerde güvenli oku
        $contents = null;
        $uploaded = $data['upload_xml'] ?? null;

        // Livewire TemporaryUploadedFile
        if (is_object($uploaded) && method_exists($uploaded, 'get')) {
            $contents = $uploaded->get();
        }

        // Standart UploadedFile
        if ($contents === null && is_object($uploaded) && method_exists($uploaded, 'getRealPath')) {
            $contents = @file_get_contents($uploaded->getRealPath()) ?: null;
        }

        // Bazı durumlarda request()->file ile gelebilir
        if ($contents === null) {
            $reqFile = request()->file('upload_xml');
            if ($reqFile) {
                $contents = @file_get_contents($reqFile->getRealPath()) ?: null;
            }
        }

        if (is_string($contents) && $contents !== '') {
            $path = 'exports/' . $run->publish_token . '.xml';
            Storage::disk('public')->put($path, $contents);
            $run->storage_path = $path;
            $run->save();
        } else {
            // Dosya yoksa minimum şablon yaz
            $run->writeXml('<?xml version="1.0" encoding="UTF-8"?><Root><Products/></Root>');
        }

        return $run;
    }
}
