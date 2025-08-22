<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Export Runs';
    protected static ?string $modelLabel = 'Export Run';
    protected static ?string $pluralModelLabel = 'Export Runs';
    protected static ?string $navigationGroup = 'Exports';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('export_profile_id')
                    ->label('Profile')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'done' => 'success',
                        'failed', 'error' => 'danger',
                        'running', 'building' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('product_count')
                    ->label('Count')
                    ->sortable(),

                TextColumn::make('published_at')
                    ->dateTime('Y-m-d H:i')
                    ->label('Published')
                    ->sortable(),

                TextColumn::make('path')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->label('Path'),

                TextColumn::make('public_url')
                    ->label('Public URL')
                    ->url(fn (ExportRun $record) => $record->public_url ?? null, true)
                    ->copyable(),

                TextColumn::make('pretty_url')
                    ->label('Pretty URL')
                    ->url(fn (ExportRun $record) => $record->pretty_url ?? null, true)
                    ->copyable(),

                TextColumn::make('publish_token')
                    ->label('Token')
                    ->limit(24)
                    ->copyable(),

                IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'done' => 'Done',
                        'running' => 'Running',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListExportRuns::route('/'),
            'view'  => Pages\ViewExportRun::route('/{record}'),
        ];
    }
}
