<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportProfileResource\Pages;
use App\Models\ExportProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
class ExportProfileResource extends Resource
{
    protected static ?string $model = ExportProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationGroup(): ?string
    {
        return 'Entegrasyon';
    }

    public static function getNavigationSort(): ?int
    {
        return 10; // istersen 1 yap, en üstte dursun
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Select::make('marketplace_id')
                    ->relationship('marketplace', 'name')
                    ->required(),
                Forms\Components\TextInput::make('min_margin')->numeric()->required(),
                Forms\Components\TextInput::make('commission_percent')->numeric()->required(),
                Forms\Components\TextInput::make('vat_percent')->numeric()->required(),
                Forms\Components\TextInput::make('rounding')->numeric()->suffix('.99')->hint('0.99 gibi')->nullable(),
                Forms\Components\Toggle::make('is_active')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('marketplace.name') // ilişkili isim
                    ->label('Marketplace')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('min_margin')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_percent')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_percent')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rounding')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
->actions([
    // ... mevcut aksiyonların ...

    Action::make('build_xml')
        ->label('XML Oluştur')
        ->icon('heroicon-o-document-plus')
        ->action(function (\App\Models\ExportProfile $record) {
            $run = app(\App\Services\ExportPublisher::class)->buildAndPublishFromProfile($record);

            if (class_exists(Notification::class)) {
                Notification::make()
                    ->title('XML Oluşturuldu')
                    ->body('Link: ' . $run->public_url)
                    ->success()
                    ->send();
            }
        })
        ->successRedirectUrl(route('filament.kadirmertozden.resources.export-runs.index')), // panel/route adını kendi paneline göre düzelt
])

->bulkActions([
    // ... mevcut bulk aksiyonların ...

    BulkAction::make('bulk_build_xml')
        ->label('Seçilen Profillerden XML Oluştur')
        ->icon('heroicon-o-document-plus')
        ->action(function ($records) {
            $svc = app(\App\Services\ExportPublisher::class);
            foreach ($records as $profile) {
                $svc->buildAndPublishFromProfile($profile);
            }
            if (class_exists(Notification::class)) {
                Notification::make()
                    ->title('XML\'ler oluşturuldu')
                    ->success()
                    ->send();
            }
        })
        ->successRedirectUrl(route('filament.kadirmertozden.resources.export-runs.index')), // panel/route adını kendi paneline göre düzelt
]);

    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExportProfiles::route('/'),
            'create' => Pages\CreateExportProfile::route('/create'),
            'edit' => Pages\EditExportProfile::route('/{record}/edit'),
        ];
    }
}
