<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    // XML yapıştırıldıysa dosyaya yazalım
    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();
        $record = $this->record;

        if (empty($record->path) && !empty($data['xml_content'])) {
            $dir = 'exports/'.$record->export_profile_id.'/manual';
            $name = now()->format('Ymd_His').'.xml';
            $path = $dir.'/'.$name;

            Storage::put($path, $data['xml_content']);
            $record->path = $path;
            $record->status = 'manual';
            $record->save();
        }
    }
}
