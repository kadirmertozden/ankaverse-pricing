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
use Illuminate\Support\Facades\URL;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';
    protected static ?string $navigationGroup = 'XML Exports';
    protected static ?string $modelLabel = 'Export Run';
    protected static ?string $pluralModelLabel = 'Export Runs';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('İsim')
                ->placeholder('Örn: HB Günlük Feed (08:00)')
                ->maxLength(255)
                ->required(),

            Forms\Components\FileUpload::make('xml_upload')
                ->label('XML Yükle')
                ->helperText('XML seçin. Ürün sayısı otomatik hesaplanır; linkler kendiliğinden oluşur.')
                ->acceptedFileTypes(['application/xml', 'text/xml', '.xml'])
                ->disk(config('filesystems.default', 'public'))
                ->directory('export_tmp')
                ->preserveFilenames()
                ->maxSize(10240)
                ->dehydrated(false)
                ->columnSpanFull(),

            // Edit’te bilgilendirme amaçlı
            Forms\Components\TextInput::make('publish_token')->label('Token')->disabled()
                ->visible(fn ($livewire) => $livewire instanceof Pages\EditExportRun),
            Forms\Components\TextInput::make('path')->label('Public URL')->disabled()
                ->visible(fn ($livewire) => $livewire instanceof Pages\EditExportRun),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label('#'),
                Tables\Columns\TextColumn::make('name')->label('İsim')->searchable(),
                Tables\Columns\TextColumn::make('publish_token')->label('Token')->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('product_count')->label('Ürün')->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->label('Path')
                    ->url(fn (ExportRun $r) => self::publicUrl($r), true)
                    ->copyable()
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')->label('Yayınlanma')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->url(fn (ExportRun $r) => self::publicUrl($r))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ExportRun $r) => URL::temporarySignedRoute(
                        'exports.download',
                        now()->addMinutes(10),
                        ['run' => $r->id]
                    ))
                    ->openUrlInNewTab()
                    ->disabled(fn (ExportRun $r) => !self::fileExists($r)),

                Tables\Actions\Action::make('xmlEdit')
                    ->label('XML Düzenle')
                    ->icon('heroicon-m-pencil-square')
                    ->form([
                        Forms\Components\Textarea::make('xml')
                            ->rows(22)
                            ->required()
                            ->afterStateHydrated(function (Forms\Components\Textarea $component, ?ExportRun $record) {
                                if (!$record) return;
                                $disk = $record->storage_disk ?? config('filesystems.default', 'public');
                                if ($record->storage_path && Storage::disk($disk)->exists($record->storage_path)) {
                                    $component->state(Storage::disk($disk)->get($record->storage_path));
                                } else {
                                    $component->state('');
                                }
                            }),
                    ])
                    ->action(function (ExportRun $record, array $data) {
                        if (!$record->storage_path) {
                            $record->storage_path = 'exports/' . $record->id . '/feed.xml';
                            $record->save();
                        }
                        $xml  = (string) $data['xml'];
                        $disk = $record->storage_disk ?? config('filesystems.default', 'public');

                        Storage::disk($disk)->put($record->storage_path, $xml);
                        $record->product_count = self::robustCountProducts($xml);
                        $record->status = 'done';
                        $record->published_at = now();
                        $record->save();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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

    /* Helpers */
    public static function publicUrl(ExportRun $record): string
    {
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE', 'https://xml.ankaverse.com.tr')), '/');
        return $base . '/' . $record->publish_token;
    }
    public static function fileExists(ExportRun $record): bool
    {
        $disk = $record->storage_disk ?? config('filesystems.default', 'public');
        return $record->storage_path && Storage::disk($disk)->exists($record->storage_path);
    }
    public static function robustCountProducts(string $xml): int
    {
        $xml = trim($xml);
        if ($xml === '') return 0;
        $tags = ['Product','product','Urun','urun','Item','item'];
        $cnt = 0;
        $reader = new \XMLReader();
        $ok = @$reader->XML($xml, null, LIBXML_NONET | LIBXML_NOENT | LIBXML_NOWARNING | LIBXML_NOERROR);
        if ($ok) {
            try {
                while (@$reader->read()) {
                    if ($reader->nodeType === \XMLReader::ELEMENT && in_array($reader->name, $tags, true)) {
                        $cnt++;
                    }
                }
            } finally {
                $reader->close();
            }
            if ($cnt > 0) return $cnt;
        }
        foreach ($tags as $t) {
            if (preg_match_all('/<\s*' . preg_quote($t,'/') . '(\s+[^>]*)?>/i', $xml, $m)) {
                $cnt += count($m[0]);
            }
        }
        if ($cnt > 0) return $cnt;
        if (preg_match_all('/<\s*StockCode(\s+[^>]*)?>/i', $xml, $m2)) return count($m2[0]);
        return 0;
    }
}
