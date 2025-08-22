<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages\CreateExportRun;
use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages\EditExportRun;
use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages\ListExportRuns;
use App\Models\ExportRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Export Runs';
    protected static ?string $navigationGroup = 'XML Yayınlama';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Temel Bilgi')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('İsim')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('publish_token')
                            ->label('Token')
                            ->helperText('Değiştirirseniz yayın linki de değişir.')
                            ->unique(ignoreRecord: true)
                            ->default(fn () => ExportRun::generateToken())
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])->columns(3),

                Forms\Components\Section::make('XML Kaynağı')
                    ->description('Create ekranında en az birini doldurun. Edit ekranında isterseniz XML’i burada değiştirin.')
                    ->schema([
                        Forms\Components\FileUpload::make('xml_upload')
                            ->label('XML (Dosya)')
                            ->acceptedFileTypes(['text/xml', 'application/xml'])
                            ->directory('tmp') // kalıcı değil, biz dosyayı kendimiz taşıyacağız
                            ->storeFiles(false)
                            ->openable()
                            ->downloadable(),

                        Forms\Components\Textarea::make('xml_text')
                            ->label('XML (Metin olarak yapıştır)')
                            ->rows(12)
                            ->autosize(),
                    ])->columns(1),

                Forms\Components\Section::make('Sistem')
                    ->schema([
                        Forms\Components\TextInput::make('storage_path')
                            ->label('Dosya Yolu (storage)')
                            ->readOnly(),

                        Forms\Components\TextInput::make('product_count')
                            ->label('Ürün Sayısı')
                            ->numeric()
                            ->readOnly(),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('İsim')->searchable()->wrap(),
                Tables\Columns\ToggleColumn::make('is_active')->label('Aktif'),
                Tables\Columns\TextColumn::make('product_count')->label('Ürün')->sortable(),
                Tables\Columns\TextColumn::make('storage_path')->label('Dosya Yolu')->toggleable(isToggledHiddenByDefault: false)->wrap(),
                Tables\Columns\TextColumn::make('publish_token')->label('Token')->copyable(),

                // Yayın Linki sütunu (closure imzası DÜZGÜN)
                Tables\Columns\TextColumn::make('publish_url')
                    ->label('Yayın Linki')
                    ->getStateUsing(fn (ExportRun $record): string => $record->publish_url)
                    ->copyable()
                    ->url(fn (ExportRun $record): string => $record->publish_url, shouldOpenInNewTab: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturuldu')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('view_xml')
                    ->label('Görüntüle')
                    ->url(fn (ExportRun $record): string => $record->publish_url, true)
                    ->icon('heroicon-o-eye'),

                Tables\Actions\Action::make('download_xml')
                    ->label('İndir')
                    ->url(fn (ExportRun $record): string => $record->download_url, true)
                    ->icon('heroicon-o-arrow-down-tray'),

                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('XML Yükle'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExportRuns::route('/'),
            'create' => CreateExportRun::route('/create'),
            'edit' => EditExportRun::route('/{record}/edit'),
        ];
    }

    /**
     * Yardımcı: XML string geçerli mi ve ürün sayısı kaç?
     */
    public static function analyzeXml(string $xml): int
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return -1; // geçersiz
        }

        // <Product> say
        $xpath = new \DOMXPath($dom);
        $count = 0;
        // Hem <Root><Products><Product> hem de direkt <Products><Product> destekle
        foreach (['//Products/Product', '/Root/Products/Product'] as $q) {
            $nodelist = $xpath->query($q);
            if ($nodelist && $nodelist->length > 0) {
                $count = $nodelist->length;
                break;
            }
        }
        if ($count === 0) {
            // Yine de tüm Product etiketlerini sayalım
            $count = $xpath->query('//Product')?->length ?? 0;
        }
        return $count;
    }

    /**
     * Yardımcı: XML’i public diske yaz ve model alanlarını güncelle.
     */
    public static function persistXml(ExportRun $record, ?string $xmlText, ?string $uploadedPath): void
    {
        $xml = null;

        if ($uploadedPath) {
            // Livewire tmp dosyası => gerçek içerik
            $xml = file_get_contents($uploadedPath);
        } elseif ($xmlText) {
            $xml = $xmlText;
        }

        if ($xml === null || trim($xml) === '') {
            throw new \RuntimeException('Geçersiz veya boş XML. Lütfen geçerli bir XML girin.');
        }

        // Geçerlilik + ürün sayısı
        $count = self::analyzeXml($xml);
        if ($count < 0) {
            throw new \RuntimeException('XML doğrulanamadı. Lütfen biçimi kontrol edin.');
        }

        $disk = Storage::disk('public');
        $path = 'exports/' . $record->publish_token . '.xml';
        $disk->put($path, $xml);

        $record->storage_path = $path;
        $record->product_count = $count;
        $record->save();
    }
}
