<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExportRun extends EditRecord
{
    protected static string $resource = ExportRunResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Edit ekranında mevcut XML içeriğini textarea'ya bas
        $xml = $this->record->readXmlOrNull() ?? '';
        $data['xml_content'] = $xml;
        return $data;
    }

    /**
     * Filament v3 imzası: handleRecordUpdate(Model $record, array $data): Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ExportRun $r */
        $r = $record;

        $r->name = $data['name'] ?? $r->name;
        $r->source_url = $data['source_url'] ?? $r->source_url;
        $r->auto_sync = isset($data['auto_sync']) ? (bool)$data['auto_sync'] : $r->auto_sync;
        if (isset($data['sync_interval_minutes']) && $data['sync_interval_minutes'] !== null) {
            $r->sync_interval_minutes = (int)$data['sync_interval_minutes'];
        }

        // XML içerik düzenlendi mi?
        if (array_key_exists('xml_content', $data) && is_string($data['xml_content'])) {
            $xml = trim($data['xml_content']);

            if ($xml !== '') {
                // Parse test (geçersizse exception at)
                try {
                    new \SimpleXMLElement($xml);
                } catch (\Throwable $e) {
                    throw new \RuntimeException('Geçersiz veya boş XML. Lütfen geçerli bir XML girin.');
                }

                // Token değişmeden dosyaya yaz
                $r->writeXml($xml);
            }
        }

        $r->save();

        return $r;
    }
}
