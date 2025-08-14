<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Models\ExportRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

// KOLONLAR
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

// AKSİYONLAR
use Filament\Tables\Actions\Action;

// (opsiyonel bildirim kullanacaksan)
use Filament\Notifications\Notification;


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
    ->disk('local')
    ->directory(fn ($get) => 'exports/'.$get('export_profile_id').'/manual')
    ->visibility('private')
    ->acceptedFileTypes(['application/xml','text/xml'])
    ->maxSize(2048)
    ->rules(['required_without:xml_content']), // KB
            ])->columns(1),

            Forms\Components\Fieldset::make('XML Yapıştır')->schema([
                Forms\Components\Textarea::make('xml_content')
                    ->rows(18)
    ->dehydrated(false)
    ->rules(['required_without:path'])
                    ->helperText('XML içeriğini burada düzenleyip kaydedebilirsin.'),
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
            TextColumn::make('path')->label('Path')->limit(60)->wrap(),
            IconColumn::make('is_public')->label('Public')->boolean(),
            TextColumn::make('published_at')->label('Published')->dateTime(),

            TextColumn::make('public_url')
                ->label('Public URL')
                ->url(fn (ExportRun $record) => $record->public_url, true)
                ->copyable()
                ->copyMessage('Kopyalandı'),
        ])
        ->actions([
            Action::make('publish_to_r2')
                ->label('R2’ye Yükle / Yenile')
                ->icon('heroicon-o-cloud-arrow-up')
                ->action(function (ExportRun $record) {
                    app(\App\Services\ExportPublisher::class)->upload($record);
                    if (class_exists(Notification::class)) {
                        Notification::make()
                            ->title('Yüklendi')
                            ->body('R2’ye yüklendi: ' . $record->public_url)
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

            Action::make('copy_link')
                ->label('Linki Kopyala')
                ->icon('heroicon-o-clipboard')
                ->action(fn (ExportRun $record) => null)
                ->copyable()
                ->copyableState(fn (ExportRun $record) => $record->public_url)
                ->copyMessage('Kopyalandı'),
        ]);
}

}
