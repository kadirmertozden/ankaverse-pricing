<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportProfile;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CreateExportRun extends CreateRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Profil boşsa ilk profili ata (DB null kabul ediyor ama UX için atanması iyi)
        if (empty($data['export_profile_id'])) {
            $data['export_profile_id'] = ExportProfile::query()->value('id');
        }

        // FileUpload canlı dosyasını al (storeFiles(false) olduğundan temp path)
        /** @var UploadedFile|null $upload */
        $upload = $this->form->getComponent('xml_upload')?->getUploadedFile();

        if ($upload instanceof UploadedFile) {
            $contents = $upload->getContent() ?? file_get_contents($upload->getRealPath());
            $data['xml'] = $contents; // Model save() içinde normalize edilip dosyaya yazılacak
        }

        return $data;
    }
}
