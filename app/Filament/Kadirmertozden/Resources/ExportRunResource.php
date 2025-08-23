<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                ->label('İsim')->maxLength(255)->required(),

            // Geçici yükleme yolunu tutan gizli alan (dehydrate=true!)
            Forms\Components\Hidden::make('xml_tmp')->dehydrated(true),

            Forms\Components\FileUpload::make('xml_upload')
                ->label('XML Yükle')
                ->acceptedFileTypes(['application/xml','text/xml','.xml'])
                ->disk('public')
                ->directory('export_tmp')
                ->preserveFilenames()
                ->maxSize(10240)
                ->dehydrated(false)
                ->columnSpanFull()
                ->required(fn ($livewire) => $livewire instanceof Pages\CreateExportRun)
                ->afterStateUpdated(function ($state, Set $set) {
                    // state dizi gelebilir
                    $path = is_array($state) ? ($state[0] ?? null) : $state;
                    $set('xml_tmp', $path ?: null);
                }),

            Forms\Components\TextInput::make('publish_token')
                ->label('Token')->helperText('BÜYÜK harf/rakam. Değiştirirsen link de değişir.')
                ->visible(fn ($livewire) => $livewire instanceof Pages\EditExportRun)
                ->maxLength(64),

            Forms\Components\TextInput::make('storage_path')
                ->label('Dosya Yolu (storage)')
                ->default(fn (?ExportRun $r) => $r?->storage_path)
                ->disabled()
                ->visible(fn ($livewire) => $livewire instanceof Pages\EditExportRun),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('İsim')->searchable()->wrap(),

                Tables\Columns\TextColumn::make('publish_token')
                    ->label('Token')->copyable(),

                Tables\Columns\TextColumn::make('product_count')
                    ->label('Ürün')->sortable(),

                // Public URL (hesaplanan sütun) — getStateUsing ile, container sorunu yok
                Tables\Columns\TextColumn::make('public_url')
                    ->label('Public URL')
                    ->getStateUsing(fn (ExportRun $r) => self::publicUrl($r))
                    ->url(fn (ExportRun $r) => self::publicUrl($r), true)
                    ->copyable()
                    ->limit(64),

                // Storage path — getStateUsing ile
                Tables\Columns\TextColumn::make('storage_path_view')
                    ->label('Dosya Yolu (storage)')
                    ->getStateUsing(fn (ExportRun $r) => $r->storage_path ?: ('exports/' . $r->publish_token . '.xml'))
                    ->copyable()
                    ->limit(64),

                Tables\Columns\TextColumn::make('created_at')->label('Oluşturuldu')->dateTime('Y-m-d H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Görüntüle')->icon('heroicon-o-eye')
                    ->url(fn (ExportRun $r) => self::publicUrl($r), true),

                Tables\Actions\Action::make('download')
                    ->label('İndir')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ExportRun $r) => route('exports.download', ['token' => $r->publish_token]), true),

                // XML içerik düzenleme – modal
                Tables\Actions\Action::make('xmlEdit')
                    ->label('XML Düzenle')->icon('heroicon-m-pencil-square')->modalWidth('7xl')
                    ->form([
                        Forms\Components\Textarea::make('xml')->rows(22)->required()
                            ->afterStateHydrated(function (Forms\Components\Textarea $component, ?ExportRun $record) {
                                if (! $record) return;
                                $disk = 'public';
                                $path = $record->storage_path ?: ('exports/' . $record->publish_token . '.xml');
                                if (Storage::disk($disk)->exists($path)) {
                                    $component->state(Storage::disk($disk)->get($path));
                                } else {
                                    $component->state(
"<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Products>
  <!-- Ürünleri buraya yerleştirin -->
</Products>"
                                    );
                                }
                            }),
                    ])
                    ->action(function (ExportRun $record, array $data) {
                        try {
                            $raw = (string)($data['xml'] ?? '');
                            if (trim($raw) === '') {
                                throw new \RuntimeException('Boş XML kaydedilemez.');
                            }

                            $xml = self::makeWellFormed($raw);

                            $disk = 'public';
                            $desired = 'exports/' . $record->publish_token . '.xml';
                            Storage::disk($disk)->put($desired, $xml);

                            $record->storage_path  = $desired;
                            $record->product_count = self::robustCountProducts($xml);
                            $record->published_at  = now();
                            $record->status        = 'done';
                            $record->save();

                            Notification::make()->title('XML kaydedildi')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('XML kaydedilemedi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('XML Yükle'),
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

    /* === Helpers === */

    public static function publicUrl(ExportRun $record): string
    {
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE','https://xml.ankaverse.com.tr')), '/');
        return $base . '/' . $record->publish_token;
    }

    public static function makeWellFormed(string $input): string
    {
        $xml = self::sanitizeXml($input);
        if (self::isValidXml($xml)) return $xml;

        $wrapped = "<Products>\n" . $xml . "\n</Products>";
        if (self::isValidXml($wrapped)) return $wrapped;

        return "<Products><Raw><![CDATA[" . self::stripCdataEnd($input) . "]]></Raw></Products>";
    }

    public static function sanitizeXml(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml ?? '');
        $xml = ltrim($xml);
        $pos = strpos($xml, '<');
        if ($pos !== false && $pos > 0) $xml = substr($xml, $pos);

        // CDATA koruma
        $ph = []; $i = 0;
        $xml = preg_replace_callback('/<!\[CDATA\[(.*?)\]\]>/s', function ($m) use (&$ph,&$i) {
            $k="__CD_{$i}__"; $ph[$k]=$m[0]; $i++; return $k;
        }, $xml);

        // Kaçak & işaretlerini düzelt
        $xml = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|#[0-9]+;|#x[0-9A-Fa-f]+;|[A-Za-z][A-Za-z0-9]+;)/', '&amp;', $xml);

        if ($ph) $xml = str_replace(array_keys($ph), array_values($ph), $xml);
        return $xml;
    }

    public static function stripCdataEnd(string $text): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $text);
    }

    public static function isValidXml(string $xml): bool
    {
        $xml = trim($xml);
        if ($xml === '' || !str_starts_with(ltrim($xml), '<')) return false;
        $prev = libxml_use_internal_errors(true);
        $ok = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET) !== false;
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        return $ok;
    }

    public static function robustCountProducts(string $xml): int
    {
        $xml = trim($xml); if ($xml === '') return 0;
        $tags = ['Product','product','Urun','urun','Item','item'];
        $cnt=0; $r=new \XMLReader();
        $ok = @$r->XML($xml, null, LIBXML_NONET|LIBXML_NOENT|LIBXML_NOWARNING|LIBXML_NOERROR);
        if ($ok) { try { while (@$r->read()) { if ($r->nodeType===\XMLReader::ELEMENT && in_array($r->name,$tags,true)) { $cnt++; } } } finally { $r->close(); } if ($cnt>0) return $cnt; }
        foreach ($tags as $t) { if (preg_match_all('/<\s*'.preg_quote($t,'/').'(\s+[^>]*)?>/i', $xml, $m)) { $cnt += count($m[0]); } }
        if ($cnt>0) return $cnt;
        if (preg_match_all('/<\s*StockCode(\s+[^>]*)?>/i', $xml, $m2)) return count($m2[0]);
        return 0;
    }
}
