<?php

namespace App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;

use App\Filament\Kadirmertozden\Resources\ExportRunResource; // ← önemli
use App\Models\ExportRun;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class ListExportRuns extends ListRecords
{
    protected static string $resource = ExportRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('xml_yukle')
                ->label('XML Yükle ve Yayınla')
                ->icon('heroicon-m-arrow-up-tray')
                ->modalHeading('XML Dosyası Yükle')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('xml_file')
                        ->label('XML Dosyası')
                        ->disk('public')                         // storage/app/public
                        ->directory('exports/manual')            // storage/app/public/exports/manual
                        ->visibility('public')
                        ->preserveFilenames()
                        ->acceptedFileTypes(['application/xml','text/xml'])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data) {
                    // FileUpload bileşeni dosyayı public diske kaydetti
                    $storedPath = $data['xml_file']; // örn: exports/manual/myfeed.xml
                    $publicUrl  = Storage::disk('public')->url($storedPath);

                    // ExportRun kaydı oluştur
                    $run = ExportRun::create([
                        'export_profile_id' => 1, // istersen formdan alabilirsin
                        'status'            => 'done',
                        'path'              => $storedPath, 
                        'publish_token'     => Str::random(32),
                        'is_public'         => true,
                        'published_at'      => now(),
                        'product_count'     => null,
                        'error'             => null,
                    ]);

                    Notification::make()
                        ->title('XML yüklendi ve yayınlandı')
                        ->body("URL: {$publicUrl}\nRun ID: {$run->id}")
                        ->success()
                        ->send();
                })
                ->successNotification(null), // Biz kendimiz Notification bastık
        ];
    }
}
