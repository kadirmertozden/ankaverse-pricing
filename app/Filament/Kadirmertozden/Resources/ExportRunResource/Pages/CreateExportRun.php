<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $state = $this->form->getState();
        $xml   = $state['xml_content'] ?? null;

        if ($xml && empty($data['path'])) {
            $dir  = 'exports/'.$data['export_profile_id'].'/manual';
            $name = 'manual-'.now()->format('Ymd-His').'.xml';
            Storage::disk('local')->put("$dir/$name", $xml);
            $data['path'] = "$dir/$name";
        }

        $data['status']        = $data['status']        ?? 'manual';
        $data['product_count'] = $data['product_count'] ?? 0;

        return $data;
    }
}
