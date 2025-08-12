<?php

namespace App\Filament\Kadirmertozden\Resources\MarketplaceResource\Pages;

use App\Filament\Kadirmertozden\Resources\MarketplaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaces extends ListRecords
{
    protected static string $resource = MarketplaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
