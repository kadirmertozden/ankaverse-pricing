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
    // Eğer yükleme yapılmadıysa ama xml_content varsa dosyaya yaz:
    if (empty($data['path']) && !empty($this->data['xml_content'])) {
        $profileId = $data['export_profile_id'];
        $dir = "exports/{$profileId}/manual";
        $filename = 'manual-'.now()->format('Ymd-His').'.xml';
        $full = "{$dir}/{$filename}";

        Storage::disk('local')->put($full, $this->data['xml_content']);
        $data['path'] = $full;
    }

    // ilk durumlar:
    $data['status'] = $data['status'] ?? 'manual';
    $data['product_count'] = $data['product_count'] ?? 0;

    return $data;
}

}
