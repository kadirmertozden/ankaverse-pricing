<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
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
                ->label('İsim')->maxLength(255)->required(),

            Forms\Components\FileUpload::make('xml_upload')
                ->label('XML Yükle')
                ->acceptedFileTypes(['application/xml','text/xml','.xml'])
                ->disk('public')->directory('export_tmp')
                ->preserveFilenames()->maxSize(10240)
                ->dehydrated(false)->columnSpanFull()
                // YENİ: Create ekranında zorunlu
                ->required(fn ($livewire) => $livewire instanceof Pages\CreateExportRun),

            // Edit’te token düzenlenebilir, path salt-okunur (mevcut davranış)
            Forms\Components\TextInput::make('publish_token')
                ->label('Token (16–64, A–Z/0–9)')
                ->visible(fn ($livewire) => $livewire instanceof Pages\EditExportRun)
                ->maxLength(64),

            Forms\Components\TextInput::make('path')
                ->label('Public URL')->disabled()
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
                Tables\Columns\TextColumn::make('path')->label('Path (Public URL)')
                    ->url(fn (ExportRun $r) => self::publicUrl($r), true)->copyable()->limit(60)->toggleable(),
                Tables\Columns\TextColumn::make('storage_path')->label('Dosya Yolu (storage)')
                    ->formatStateUsing(fn(?string $s, ExportRun $r) => $s ?: ('exports/'.$r->publish_token.'.xml'))
                    ->copyable()->limit(60)->toggleable(),
                Tables\Columns\TextColumn::make('published_at')->label('Yayınlanma')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->label('View')
                    ->url(fn (ExportRun $r) => self::publicUrl($r))->openUrlInNewTab(),
                Tables\Actions\Action::make('download')->label('Download')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ExportRun $r) => URL::temporarySignedRoute('exports.download', now()->addMinutes(10), ['run'=>$r->id]))
                    ->openUrlInNewTab()->disabled(fn (ExportRun $r) => !self::fileExists($r)),
                // XML Düzenle aksiyonun mevcut halini aynen kullanmaya devam edebilirsin
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

    /* === Helpers (mevcut sürümündekiyle aynı) === */
    public static function publicUrl(ExportRun $record): string
    {
        $base = rtrim(config('services.xml_public_base', env('XML_PUBLIC_BASE','https://xml.ankaverse.com.tr')), '/');
        return $base . '/' . $record->publish_token;
    }
    public static function fileExists(ExportRun $record): bool
    {
        $disk = 'public';
        $path = $record->storage_path ?: ('exports/' . $record->publish_token . '.xml');
        return Storage::disk($disk)->exists($path);
    }

    /* Eğer önceki cevapta sağlanan sanitize/makeWellFormed yardımcılarını kullanıyorsan
       bu sınıfta kalabilir; yoksa kaldırabilirsin. */
}
