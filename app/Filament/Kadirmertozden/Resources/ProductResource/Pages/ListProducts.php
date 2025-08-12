<?php

namespace App\Filament\Kadirmertozden\Resources\ProductResource\Pages;

use App\Filament\Kadirmertozden\Resources\ProductResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('priceBuild')
                ->label('Tümünü Fiyatla (Profil #1 / Aktif)')
                ->icon('heroicon-o-calculator')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        // 1) Önce id=1 var mı?
                        $profileId = DB::table('pricing_profiles')->where('id', 1)->value('id');

                        // 2) Yoksa ilk aktif profili al
                        if (!$profileId) {
                            $profileId = DB::table('pricing_profiles')
                                ->where('is_active', 1)
                                ->orderBy('id')
                                ->value('id');
                        }

                        // 3) Hâlâ yoksa kullanıcıya bildir
                        if (!$profileId) {
                            Notification::make()
                                ->title('Profil bulunamadı')
                                ->body('Lütfen bir PricingProfile kaydı oluşturun.')
                                ->danger()
                                ->send();
                            return;
                        }

                        Artisan::call('price:build', ['--profile_id' => $profileId]);
                        $out = trim(Artisan::output()) ?: "Komut tamamlandı. (Profil #{$profileId})";

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
                        throw $e;
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
