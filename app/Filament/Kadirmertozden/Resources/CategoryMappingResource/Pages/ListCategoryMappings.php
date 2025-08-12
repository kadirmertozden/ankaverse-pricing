<?php

namespace App\Filament\Kadirmertozden\Resources\CategoryMappingResource\Pages;

use App\Filament\Kadirmertozden\Resources\CategoryMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoryMappings extends ListRecords
{
    protected static string $resource = CategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
