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
    protected static ?string $modelLabel = 'Export Run';
    protected static ?string $pluralModelLabel = 'Export Runs';
    protected static ?string $navigationGroup = 'Exports';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('export_profile_id')
                    ->numeric()
                    ->label('Profile ID')
                    ->required(),

                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->maxLength(32)
                    ->required(),

                Forms\Components\TextInput::make('path')
                    ->label('Path')
                    ->maxLength(255),

                Forms\Components\TextInput::make('publish_token')
                    ->label('Publish Token')
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_public')
                    ->label('Public')
                    ->default(false),

                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Published At')
                    ->seconds(false),

                Forms\Components\TextInput::make('product_count')
                    ->numeric()
                    ->label('Product Count'),

                Forms\Components\Textarea::make('error')
                    ->label('Error')
                    ->rows(3),

                // (İsteğe bağlı) Modelde accessor varsa otomatik dolar;
                // manuel giriş gerekmesin istersen bunları kaldırabilirsin.
                Forms\Components\TextInput::make('public_url')
                    ->label('Public URL')
                    ->maxLength(255),

                Forms\Components\TextInput::make('pretty_url')
                    ->label('Pretty URL')
                    ->maxLength(255),
            ]);
    }

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
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('pretty_url')
                    ->label('Pretty URL')
                    ->url(fn (ExportRun $record) => $record->pretty_url ?? null, true)
                    ->copyable()
                    ->toggleable(),

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
                Tables\Actions\EditAction::make(),     // 👈 Düzenle
                Tables\Actions\DeleteAction::make(),   // 👈 Sil
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExportRuns::route('/'),
            'create' => Pages\CreateExportRun::route('/create'),  // 👈 Ekle
            'view'   => Pages\ViewExportRun::route('/{record}'),
            'edit'   => Pages\EditExportRun::route('/{record}/edit'), // 👈 Düzenle
        ];
    }
}
