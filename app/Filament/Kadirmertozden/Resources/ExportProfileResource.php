<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ExportProfileResource\Pages;
use App\Models\ExportProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generateXml')
                    ->label('XML Oluştur')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        \App\Jobs\GenerateExportXmlJob::dispatch($record->id);
                        \Filament\Notifications\Notification::make()
                            ->title('XML oluşturma başlatıldı')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
