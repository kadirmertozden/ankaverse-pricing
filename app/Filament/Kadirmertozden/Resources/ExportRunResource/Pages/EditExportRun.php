<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

protected function mutateFormDataBeforeSave(array $data): array
{
    if (!empty($this->data['xml_content'])) {
        $record = $this->getRecord();
        $full = $record->path;

        if (!$full) {
            $profileId = $data['export_profile_id'] ?? $record->export_profile_id;
            $dir = "exports/{$profileId}/manual";
            $filename = 'manual-'.now()->format('Ymd-His').'.xml';
            $full = "{$dir}/{$filename}";
        }

        Storage::disk('local')->put($full, $this->data['xml_content']);
        $data['path'] = $full;
    }

    return $data;
}

}
