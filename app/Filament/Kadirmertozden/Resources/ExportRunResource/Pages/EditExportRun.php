<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\UploadedFile;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var UploadedFile|null $upload */
        $upload = $this->form->getComponent('xml_upload')?->getUploadedFile();

        if ($upload instanceof UploadedFile) {
            $contents = $upload->getContent() ?? file_get_contents($upload->getRealPath());
            $data['xml'] = $contents; // Model saved() içinde normalize + dosyaya yaz
        }

        return $data;
    }
}
