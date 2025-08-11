<?php

namespace App\Filament\Kadirmertozden\Resources\ProductResource\Pages;

use App\Filament\Kadirmertozden\Resources\ProductResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('priceBuild')
                ->label('Tümünü Fiyatla (Profil #1)')
                ->requiresConfirmation()
                ->icon('heroicon-o-calculator')
                ->action(function () {
                    Artisan::call('price:build', ['--profile_id' => 1]);
                    $out = trim(Artisan::output());
                    $this->notify('success', "Fiyatlama tamamlandı.\n{$out}");
                }),

            Actions\CreateAction::make(),
        ];
    }
}
