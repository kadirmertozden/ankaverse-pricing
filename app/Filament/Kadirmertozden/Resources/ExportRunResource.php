<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportRun;
use App\Models\ExportProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    public static function getNavigationGroup(): ?string { return 'Entegrasyon'; }
    public static function getNavigationSort(): ?int { return 12; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Manuel XML ekleme için:
            Forms\Components\Select::make('export_profile_id')
                ->label('Profil')
                ->options(ExportProfile::query()->pluck('name','id'))
                ->required(),

            // Yükleme seçeneği
            Forms\Components\Fieldset::make('XML Yükle')
                ->schema([
                    Forms\Components\FileUpload::make('path')
                        ->label('XML Dosyası')
                        ->directory(fn (callable $get) => 'exports/'.$get('export_profile_id').'/manual')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/xml','text/xml'])
                        ->helperText('Alternatif: Aşağıya XML içeriğini yapıştırabilirsin.')
                        ->maxSize(2048), // KB
                ])
                ->columns(1),

            // Yapıştırma seçeneği
            Forms\Components\Fieldset::make('XML Yapıştır')
                ->schema([
                    Forms\Components\Textarea::make('xml_content')
                        ->rows(18)
                        ->dehydrate(false) // DB'ye yazma; dosyaya yazacağız
                        ->helperText('XML içeriğini burada düzenleyip kaydedebilirsin.'),
                ])
                ->columns(1),

            Forms\Components\Hidden::make('status')->default('manual'),
            Forms\Components\Hidden::make('product_count')->default(0),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('exportProfile.name')
                ->label('Profil')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->colors([
                    'success' => 'done',
                    'warning' => 'running',
                    'danger'  => 'failed',
                    'gray'    => 'manual',
                    'info'    => 'queued',
                ]),

            Tables\Columns\TextColumn::make('product_count')
                ->alignRight(),

            Tables\Columns\TextColumn::make('public_url')
                ->label('Yayın Linki')
                ->getStateUsing(fn ($record) => $record->public_url) // <-- $record
                // ->copyable() // <-- YOK
                ->url(fn ($record) => $record->public_url, shouldOpenInNewTab: true)
                ->extraAttributes([
                    'style' => 'max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;',
                ]),

            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->since(),
        ])
        ->actions([
            Tables\Actions\Action::make('download')
                ->label('İndir')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn ($record) => filled($record->path) && Route::has('admin.exports.download'))
                ->url(fn ($record) => route('admin.exports.download', $record))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('publish')
                ->label('Yayınla')
                ->icon('heroicon-o-globe-alt')
                ->visible(fn ($record) => $record->path && ! $record->is_public)
                ->requiresConfirmation()
                ->action(function ($record) {
                    if (! $record->publish_token) {
                        $record->publish_token = Str::random(32);
                    }
                    $record->is_public   = true;
                    $record->published_at = now();
                    $record->save();

                    Notification::make()->title('XML yayınlandı')->success()->send();
                }),

            // "Linki Kopyala" aksiyonu kaldırıldı (v3.3.0 uyumluluğu için)

            Tables\Actions\Action::make('openLink')
                ->label('Aç')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->visible(fn ($record) => $record->is_public && $record->public_url)
                ->url(fn ($record) => $record->public_url, true),

            Tables\Actions\Action::make('regenerate')
                ->label('Linki Yenile')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn ($record) => $record->is_public)
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->publish_token = Str::random(32);
                    $record->published_at  = now();
                    $record->save();
                    Notification::make()->title('Yayın linki yenilendi')->success()->send();
                }),

            Tables\Actions\Action::make('unpublish')
                ->label('Yayını Kaldır')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn ($record) => $record->is_public)
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->is_public = false;
                    $record->save();
                    Notification::make()->title('Yayın kaldırıldı')->success()->send();
                }),

            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}
}