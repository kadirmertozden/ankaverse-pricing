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
use Illuminate\Support\Facades\Storage;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Exports';
    protected static ?string $navigationLabel = 'Export Runs';
    protected static ?string $slug = 'export-runs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('İsim')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('export_profile_id')
                    ->label('Profil')
                    ->options(fn () => ExportProfile::query()->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->required()
                    ->preload(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Forms\Components\FileUpload::make('xml_upload')
                    ->label('XML Yükle')
                    ->acceptedFileTypes(['text/xml','application/xml'])
                    ->storeFiles(false) // dosyayı biz işleyeceğiz
                    ->helperText('Sadece isim ve XML yükleyin; diğer alanlar otomatik dolar.'),

                Forms\Components\Textarea::make('xml')
                    ->label('XML Düzenle (opsiyonel)')
                    ->rows(14)
                    ->helperText('İsterseniz XML’i buradan düzenleyebilirsiniz. Yüklediğiniz dosya varsa üzerine yazılır.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('İsim')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('publish_token')->label('Token')->copyable()->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('public_url')
                    ->label('Link')
                    ->state(fn (ExportRun $record) => url("/{$record->publish_token}"))
                    ->url(fn (ExportRun $record) => url("/{$record->publish_token}"), true)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('storage_path')
                    ->label('Dosya Yolu')
                    ->state(fn (ExportRun $record) => "exports/{$record->publish_token}.xml")
                    ->copyable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->url(fn (ExportRun $record) => url("/{$record->publish_token}"))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download')
                    ->label('İndir')
                    ->url(fn (ExportRun $record) => url("/{$record->publish_token}/download")),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExportRuns::route('/'),
            'create' => Pages\CreateExportRun::route('/create'),
            'edit'   => Pages\EditExportRun::route('/{record}/edit'),
        ];
    }
}
