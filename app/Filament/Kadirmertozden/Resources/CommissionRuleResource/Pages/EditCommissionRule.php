<?php

namespace App\Filament\Kadirmertozden\Resources\CommissionRuleResource\Pages;

use App\Filament\Kadirmertozden\Resources\CommissionRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommissionRule extends EditRecord
{
    protected static string $resource = CommissionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
