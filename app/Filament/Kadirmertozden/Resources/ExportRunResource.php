<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportProfile;
use App\Models\ExportRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    public static function getNavigationGroup(): ?string
    {
        return 'Entegrasyon';
    }

    public static function getNavigationSort(): ?int
    {
        return 12;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExportRuns::route('/'),
            'create' => Pages\CreateExportRun::route('/create'),
            'edit'   => Pages\EditExportRun::route('/{record}/edit'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('export_profile_id')
                ->label('Profil')
                ->options(ExportProfile::query()->pluck('name', 'id'))
                ->required(),

            Forms\Components\Fieldset::make('XML Yükle')->schema([
                Forms\Components\FileUpload::make('path')
                    ->label('XML Dosyası')
                    ->disk('public') // public diske yazıyoruz (storage:link mevcut)
                    ->directory(fn ($get) => 'exports/' . ($get('export_profile_id') ?? 1) . '/manual')
                    ->visibility('public')
                    ->acceptedFileTypes(['application/xml', 'text/xml'])
                    ->maxSize(2048) // KB
                    ->rules(['required_without:xml_content']),
            ])->columns(1),

            Forms\Components\Fieldset::make('XML Yapıştır')->schema([
                Forms\Components\Textarea::make('xml_content')
                    ->rows(18)
                    ->dehydrated(false)
                    ->helperText('XML içeriğini burada düzenleyip kaydedebilirsin.')
                    ->rules(['required_without:path']),
            ])->columns(1),

            Forms\Components\Hidden::make('status')->default('manual'),
            Forms\Components\Hidden::make('product_count')->default(0),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                // path sütununu KOLONLARDA göster (actions içine koyma!)
                TextColumn::make('path')
                    ->label('XML Yolu')
                    ->limit(60)
                    ->wrap()
                    ->url(fn (ExportRun $record) => Storage::disk('public')->url($record->path), true)
                    ->openUrlInNewTab(),
                IconColumn::make('is_public')->boolean()->label('Public'),
                TextColumn::make('published_at')->dateTime()->label('Published'),
                TextColumn::make('public_url')
                    ->label('Public URL')
                    ->url(fn (ExportRun $record) => $record->public_url, true),
            ])

            ->actions([
                Action::make('publish_to_r2')
                    ->label('R2’ye Yükle / Yenile')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->action(function (ExportRun $record) {
                        app(\App\Services\ExportPublisher::class)->upload($record);
                        if (class_exists(\Filament\Notifications\Notification::class)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Yüklendi')
                                ->body('R2’ye yüklendi: ' . $record->public_url)
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('delete_xml')
                    ->label('XML Sil')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ExportRun $record) {
                        app(\App\Services\ExportPublisher::class)->delete($record);
                        if (class_exists(\Filament\Notifications\Notification::class)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Silindi')
                                ->body('XML yayından kaldırıldı.')
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('view')
                    ->label('Görüntüle')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ExportRun $record) => $record->public_url, true),

                Action::make('download')
                    ->label('İndir')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ExportRun $record) => $record->download_url, true)
                    ->openUrlInNewTab(false),

                // DB kaydını komple silmek istersen
                TableDeleteAction::make(),
            ])

            ->bulkActions([
                BulkAction::make('bulk_delete_xml')
                    ->label('Seçilenlerin XML’ini Sil')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $svc = app(\App\Services\ExportPublisher::class);
                        foreach ($records as $record) {
                            $svc->delete($record);
                        }
                        if (class_exists(\Filament\Notifications\Notification::class)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Silindi')
                                ->body('Seçilen kayıtların XML\'leri yayından kaldırıldı.')
                                ->success()
                                ->send();
                        }
                    }),
            ]);
    }
}
