<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ShippingRuleResource\Pages;
use App\Filament\Kadirmertozden\Resources\ShippingRuleResource\RelationManagers;
use App\Models\ShippingRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShippingRuleResource extends Resource
{
	public static function getNavigationGroup(): ?string
{
    return 'Entegrasyon';
}

public static function getNavigationSort(): ?int
{
    return 10; // istersen 1 yap, en üstte dursun
}

    protected static ?string $model = ShippingRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('marketplace_id')
                    ->numeric(),
                Forms\Components\TextInput::make('desi_min')
                    ->numeric(),
                Forms\Components\TextInput::make('desi_max')
                    ->numeric(),
                Forms\Components\TextInput::make('weight_min')
                    ->numeric(),
                Forms\Components\TextInput::make('weight_max')
                    ->numeric(),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('marketplace_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('desi_min')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('desi_max')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_min')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_max')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingRules::route('/'),
            'create' => Pages\CreateShippingRule::route('/create'),
            'edit' => Pages\EditShippingRule::route('/{record}/edit'),
        ];
    }
}
