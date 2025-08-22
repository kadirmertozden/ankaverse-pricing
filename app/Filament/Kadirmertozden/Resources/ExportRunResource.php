<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Export Runs';
    protected static ?string $modelLabel = 'Export Run';
    protected static ?string $pluralModelLabel = 'Export Runs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('İsim')
                ->placeholder('Örn: HB Günlük Feed (08:00)')
                ->maxLength(255),

            Forms\Components\TextInput::make('publish_token')
                ->label('Token')
                ->disabled(),

            Forms\Components\TextInput::make('path')
                ->label('Public URL (salt-okunur)')
                ->disabled(),

            Forms\Components\TextInput::make('storage_path')
                ->label('Dosya Yolu (storage)')
                ->disabled(),

            Forms\Components\TextInput::make('status')
                ->disabled(),

            Forms\Components\TextInput::make('product_count')
                ->numeric()
                ->disabled(),

            Forms\Components\DateTimePicker::make('published_at')
                ->label('Yayınlanma')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('İsim')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('product_count')->label('Ürün')->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->label('Public Link')
                    ->url(fn(ExportRun $r) => $r->path, true)
                    ->copyable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->label('Yayın Tarihi'),
            ])
            ->filters([])
            ->actions([
                Action::make('view_public')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(ExportRun $record) => $record->path)
                    ->openUrlInNewTab(),

                Action::make('edit_xml')
                    ->label('XML Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('XML Düzenle')
                    ->modalSubmitActionLabel('Kaydet')
                    ->form([
                        Forms\Components\Textarea::make('xml_content')
                            ->label('XML İçeriği')
                            ->rows(22)
                            ->required()
                            ->afterStateHydrated(function ($component, ExportRun $record) {
                                $disk = $record->storage_disk ?? config('filesystems.default', 'public');
                                $content = '';
                                if ($record->storage_path && Storage::disk($disk)->exists($record->storage_path)) {
                                    $content = Storage::disk($disk)->get($record->storage_path);
                                }
                                $component->state($content);
                            }),
                    ])
                    ->action(function (array $data, ExportRun $record) {
                        $xml = trim($data['xml_content'] ?? '');
                        if ($xml === '') {
                            throw new \RuntimeException('XML boş olamaz.');
                        }
                        // Basit güvenlik/format kontrolü (opsiyonel)
                        if (!str_contains($xml, '<?xml')) {
                            // İsterseniz bu kuralı kaldırabilirsiniz.
                            throw new \RuntimeException('Geçersiz XML: XML declaration bulunamadı.');
                        }
                        app(\App\Services\ExportPublisher::class)->overwriteXml($record, $xml);
                    })
                    ->visible(fn(ExportRun $r) => filled($r->storage_path)),

                Tables\Actions\EditAction::make()
                    ->label('Düzenle'),

                Tables\Actions\DeleteAction::make()
                    ->label('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        // Mevcut sayfalarınız varsa koruyun.
        return [
            'index' => Pages\ListExportRuns::route('/'),
            'create' => Pages\CreateExportRun::route('/create'),
            'edit' => Pages\EditExportRun::route('/{record}/edit'),
        ];
    }
}
