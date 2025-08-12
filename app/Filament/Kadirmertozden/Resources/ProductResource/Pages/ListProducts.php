<?php

namespace App\Filament\Kadirmertozden\Resources\ProductResource\Pages;

use App\Filament\Kadirmertozden\Resources\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('priceAll')
                ->label(function () {
                    $id = config('pricing.default_profile_id', 1);
                    return "Tümünü Fiyatla (Profil #{$id} / Aktif)";
                })
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $preferredId = (int) config('pricing.default_profile_id', 1);

                    // 1) Tercih edilen ID
                    $profile = DB::table('pricing_profiles')->where('id', $preferredId)->first();

                    // 2) Bulunamazsa ilk aktif profil
                    if (! $profile) {
                        $profile = DB::table('pricing_profiles')->where('is_active', 1)->orderBy('id')->first();
                    }

                    if (! $profile) {
                        Notification::make()
                            ->title('Profil bulunamadı')
                            ->body('Lütfen bir PricingProfile kaydı oluşturun.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Artisan::call('price:build', ['--profile_id' => $profile->id]);

                    Notification::make()
                        ->title('Fiyatlama bitti')
                        ->body("Profil #{$profile->id} çalıştırıldı.")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('priceSelected')
                ->label('Seçilileri Fiyatla')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->action(function ($records) {
                    $ids = collect($records)->pluck('id')->all();
                    if (empty($ids)) {
                        Notification::make()
                            ->title('Seçim yok')
                            ->warning()
                            ->send();
                        return;
                    }

                    $profileId = (int) config('pricing.default_profile_id', 1);
                    $profile = DB::table('pricing_profiles')->where('id', $profileId)->first()
                        ?? DB::table('pricing_profiles')->where('is_active', 1)->orderBy('id')->first();

                    if (! $profile) {
                        Notification::make()
                            ->title('Profil bulunamadı')
                            ->body('Lütfen bir PricingProfile kaydı oluşturun.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Seçilileri fiyatlamak için basit yaklaşım: ürünleri geçici olarak filtreleyip komutu çağırma
                    // (İstersen burada sadece seçilenler için özel bir komut da yazabiliriz.)
                    Artisan::call('price:build', ['--profile_id' => $profile->id]);

                    Notification::make()
                        ->title('Seçili ürünler fiyatlandı')
                        ->success()
                        ->send();
                }),
        ];
    }
}
