<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportRunResource\Pages;
use App\Models\ExportProfile;
use App\Models\ExportRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ExportRunResource extends Resource
{
    protected static ?string $model = ExportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    public static function getNavigationGroup(): ?string
    {
        return 'Entegrasyon';
    }

    public static function getNavigationSort(): ?int
    {
        return 12;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExportRuns::route('/'),
            'create' => Pages\CreateExportRun::route('/create'),
            'edit'   => Pages\EditExportRun::route('/{record}/edit'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('export_profile_id')
                ->label('Profil')
                ->options(ExportProfile::query()->pluck('name', 'id'))
                ->required(),

            Forms\Components\Fieldset::make('XML Yükle')->schema([
                Forms\Components\FileUpload::make('path')
    ->label('XML Dosyası')
    ->disk('local')
    ->directory(fn ($get) => 'exports/'.$get('export_profile_id').'/manual')
    ->visibility('private')
    ->acceptedFileTypes(['application/xml','text/xml'])
    ->maxSize(2048)
    ->rules(['required_without:xml_content']), // KB
            ])->columns(1),

            Forms\Components\Fieldset::make('XML Yapıştır')->schema([
                Forms\Components\Textarea::make('xml_content')
                    ->rows(18)
    ->dehydrated(false)
    ->rules(['required_without:path'])
                    ->helperText('XML içeriğini burada düzenleyip kaydedebilirsin.'),
            ])->columns(1),

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
                    ->getStateUsing(fn ($record) => $record->public_url)
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
					->visible(fn ($r) => filled($r->path) && \Storage::disk('local')->exists($r->path))
                    ->visible(fn ($record) => filled($record->path) && Route::has('admin.exports.download'))
                    ->url(fn ($record) => route('admin.exports.download', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('publish')
                    ->label('Yayınla')
                    ->icon('heroicon-o-globe-alt')
					->visible(fn ($r) => filled($r->path) && \Storage::disk('local')->exists($r->path))
                    ->visible(fn ($record) => $record->path && ! $record->is_public)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if (! $record->publish_token) {
                            $record->publish_token = Str::random(32);
                        }
                        $record->is_public    = true;
                        $record->published_at = now();
                        $record->save();

                        Notification::make()
                            ->title('XML yayınlandı')
                            ->success()
                            ->send();
                    }),

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

                        Notification::make()
                            ->title('Yayın linki yenilendi')
                            ->success()
                            ->send();
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

                        Notification::make()
                            ->title('Yayın kaldırıldı')
                            ->success()
                            ->send();
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
