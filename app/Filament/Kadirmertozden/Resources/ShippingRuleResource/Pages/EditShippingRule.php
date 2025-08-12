<?php

namespace App\Filament\Kadirmertozden\Resources\ShippingRuleResource\Pages;

use App\Filament\Kadirmertozden\Resources\ShippingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingRule extends EditRecord
{
    protected static string $resource = ShippingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
