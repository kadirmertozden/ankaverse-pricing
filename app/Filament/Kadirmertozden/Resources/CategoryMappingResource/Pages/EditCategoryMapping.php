<?php

namespace App\Filament\Kadirmertozden\Resources\CategoryMappingResource\Pages;

use App\Filament\Kadirmertozden\Resources\CategoryMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoryMapping extends EditRecord
{
    protected static string $resource = CategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
