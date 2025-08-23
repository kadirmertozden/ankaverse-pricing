<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';
    protected static ?string $navigationLabel = 'Export Runs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // İSTEK: "sadece isim + xml yükleme" — create'te bu iki alan yeter
                Forms\Components\TextInput::make('name')
                    ->label('İsim')
                    ->required()
                    ->maxLength(255),

                Forms\Components\FileUpload::make('upload_xml')
                    ->label('XML Yükle')
                    ->acceptedFileTypes(['text/xml','application/xml'])
                    ->storeFiles(false)
                    ->visibility('private')
                    ->required(fn($livewire) => $livewire instanceof Pages\CreateExportRun),

                Forms\Components\Textarea::make('xml_content')
                    ->label('XML İçeriği (Düzenle)')
                    ->rows(16)
                    ->columnSpanFull()
                    ->visible(fn($livewire) => $livewire instanceof Pages\EditExportRun)
                    ->helperText('Token değişmeden içeriği günceller.'),

                Forms\Components\Section::make('Otomatik Senkron (Opsiyonel)')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('source_url')
                            ->label('Kaynak XML URL')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('auto_sync')
                            ->label('Otomatik Senkron Aç'),
                        Forms\Components\TextInput::make('sync_interval_minutes')
                            ->label('Senkron Aralığı (dk)')
                            ->numeric()
                            ->default(30)
                            ->minValue(1),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('İsim')->searchable(),
                Tables\Columns\TextColumn::make('publish_token')
                    ->label('Token')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('public_link')
                    ->label('Link')
                    ->formatStateUsing(fn(ExportRun $r) => $r->publicUrl())
                    ->url(fn(ExportRun $r) => $r->publicUrl(), true)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('storage_path')
                    ->label('Dosya Yolu')
                    ->toggleable()
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('last_synced_at')->label('Son Senkron')->dateTime()->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(ExportRun $r) => $r->publicUrl(), true)
                    ->openUrlInNewTab()
                    ->visible(fn(ExportRun $r) => $r->storageExists()),

                Tables\Actions\Action::make('download')
                    ->label('İndir')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn(ExportRun $r) => $r->downloadUrl(), true)
                    ->openUrlInNewTab()
                    ->visible(fn(ExportRun $r) => $r->storageExists()),

                Tables\Actions\Action::make('syncNow')
                    ->label('Sync Now')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (ExportRun $record) {
                        // basit şekilde command’i tetikleyelim
                        \Artisan::call('exports:sync', [
                            '--token' => $record->publish_token,
                            '--force' => true,
                        ]);
                        $record->refresh();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExportRuns::route('/'),
            'create' => Pages\CreateExportRun::route('/create'),
            'edit' => Pages\EditExportRun::route('/{record}/edit'),
        ];
    }
}
