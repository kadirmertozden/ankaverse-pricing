<?php

namespace App\Filament\Kadirmertozden\Resources\ExportProfileResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExportProfile extends EditRecord
{
    protected static string $resource = ExportProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
