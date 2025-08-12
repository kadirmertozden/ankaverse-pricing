<?php

namespace App\Filament\Kadirmertozden\Resources\ProductResource\Pages;

use App\Filament\Kadirmertozden\Resources\ProductResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('priceBuild')
                ->label('Tümünü Fiyatla (Profil #1)')
                ->icon('heroicon-o-calculator')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        Artisan::call('price:build', ['--profile_id' => 1]);
                        $out = trim(Artisan::output()) ?: 'Komut tamamlandı.';
                        Notification::make()
                            ->title('Fiyatlama bitti')
                            ->body($out)
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Fiyatlama hatası')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        throw $e; // log’a düşsün
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
