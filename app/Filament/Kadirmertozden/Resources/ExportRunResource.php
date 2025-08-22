<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms;
use Filament\Forms\Form;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Export Runs';
    protected static ?string $navigationGroup = 'Exports';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('upload_file')
                    ->label('XML Yükle')
                    ->disk('public')
                    ->directory('exports/tmp')
                    ->preserveFilenames()
                    ->visibility('public')
                    ->acceptedFileTypes(['application/xml', 'text/xml'])
                    ->maxSize(10240) // 10 MB
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('publish_token')->label('Token')->copyable(),
                TextColumn::make('published_at')->dateTime('Y-m-d H:i'),
                TextColumn::make('path')->limit(40)->tooltip(fn ($state) => $state),
                IconColumn::make('is_public')->boolean()->label('Public'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->url(fn (ExportRun $record) => route('export-runs.download', $record))
                    ->openUrlInNewTab(),
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
            'view'   => Pages\ViewExportRun::route('/{record}'),
        ];
    }
}
