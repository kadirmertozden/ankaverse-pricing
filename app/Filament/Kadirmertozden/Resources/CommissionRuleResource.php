<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\CommissionRuleResource\Pages;
use App\Filament\Kadirmertozden\Resources\CommissionRuleResource\RelationManagers;
use App\Models\CommissionRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommissionRuleResource extends Resource
{
	public static function getNavigationGroup(): ?string
{
    return 'Entegrasyon';
}

public static function getNavigationSort(): ?int
{
    return 10; // istersen 1 yap, en üstte dursun
}

    protected static ?string $model = CommissionRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('marketplace_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('marketplace_category_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('commission_percent')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('marketplace_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('marketplace_category_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('commission_percent')
                    ->numeric()
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
            'index' => Pages\ListCommissionRules::route('/'),
            'create' => Pages\CreateCommissionRule::route('/create'),
            'edit' => Pages\EditCommissionRule::route('/{record}/edit'),
        ];
    }
}
