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
                // XML dosyası yükleme (public disk)
                Forms\Components\FileUpload::make('upload_file')
                    ->label('XML Dosyası')
                    ->disk('public')
                    ->directory('exports/tmp')
                    ->preserveFilenames()
                    ->visibility('public')
                    ->acceptedFileTypes(['application/xml', 'text/xml'])
                    ->maxSize(10240) // 10 MB
                    ->required()
                    ->helperText('XML dosyanı seç ve kaydet. Sistem otomatik yayınlayacak.'),

                Forms\Components\TextInput::make('export_profile_id')
                    ->numeric()
                    ->label('Profile ID')
                    ->required(),

                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->maxLength(32)
                    ->default('done')
                    ->required(),

                Forms\Components\Toggle::make('is_public')
                    ->label('Public')
                    ->default(true),

                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Published At')
                    ->seconds(false)
                    ->default(now()),

                Forms\Components\TextInput::make('product_count')
                    ->numeric()
                    ->label('Product Count'),

                Forms\Components\Textarea::make('error')
                    ->label('Error')
                    ->rows(2),

                // Bilgi amaçlı (opsiyonel görünsün istiyorsan bırak)
                Forms\Components\TextInput::make('publish_token')
                    ->label('Publish Token')
                    ->maxLength(255)
                    ->disabled(),

                Forms\Components\TextInput::make('path')
                    ->label('Final Path')
                    ->maxLength(255)
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->searchable(),
                TextColumn::make('export_profile_id')->label('Profile')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'done' => 'success',
                        'failed', 'error' => 'danger',
                        'running', 'building' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('product_count')->label('Count')->sortable(),
                TextColumn::make('published_at')->dateTime('Y-m-d H:i')->label('Published')->sortable(),
                TextColumn::make('path')->limit(40)->tooltip(fn ($state) => $state)->label('Path'),
                TextColumn::make('publish_token')->label('Token')->limit(26)->copyable(),
                IconColumn::make('is_public')->boolean()->label('Public'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'done' => 'Done',
                    'running' => 'Running',
                    'failed' => 'Failed',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'create' => Pages\CreateExportRun::route('/create'),   // XML Ekle burada
            'view'   => Pages\ViewExportRun::route('/{record}'),
            'edit'   => Pages\EditExportRun::route('/{record}/edit'),
        ];
    }
}
