<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource;
use App\Models\ExportRun;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Illuminate\Support\Str;

class ListExportRuns extends ListRecords
{
    protected static string $resource = ExportRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadXml')
                ->label('XML Yükle')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\Select::make('export_profile_id')
                        ->label('Profil')
                        ->options(\App\Models\ExportProfile::query()->pluck('name', 'id'))
                        ->required(),

                    Forms\Components\FileUpload::make('path')
                        ->label('XML Dosyası')
                        ->disk('public')
                        ->directory(fn ($get) => 'exports/' . ($get('export_profile_id') ?? 1) . '/manual')
                        ->visibility('public')
                        ->acceptedFileTypes(['application/xml', 'text/xml'])
                        ->maxSize(4096)
                        ->required(),
                ])
                ->action(function (array $data) {
                    // Güvenli varsayılanlar
                    $payload = [
                        'export_profile_id' => (int) ($data['export_profile_id'] ?? 1),
                        'path'              => $data['path'] ?? null,
                        'status'            => 'manual',
                        'product_count'     => 0,
                        'is_public'         => false,
                        'published_at'      => null,
                        'publish_token'     => Str::random(32),
                        'error'             => null,
                    ];

                    // Kayıt oluştur
                    /** @var \App\Models\ExportRun $run */
                    $run = ExportRun::create($payload);

                    // İstersen otomatik yayınla:
                    app(\App\Services\ExportPublisher::class)->upload($run);

                    Notification::make()
                        ->title('Yüklendi')
                        ->body('XML yüklendi ve yayınlandı.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
