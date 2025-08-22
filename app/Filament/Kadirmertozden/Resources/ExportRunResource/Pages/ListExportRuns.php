<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExportRuns extends ListRecords
{
    protected static string $resource = ExportRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('XML Yükle'),
        ];
    }
}
