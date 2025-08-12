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
        $state = $this->form->getState();
        $xml   = $state['xml_content'] ?? null;

        if ($xml) {
            $dir  = 'exports/'.$data['export_profile_id'].'/manual';
            $name = 'manual-'.now()->format('Ymd-His').'.xml';
            Storage::disk('local')->put("$dir/$name", $xml);
            $data['path'] = "$dir/$name";
        }

        return $data;
    }
}
