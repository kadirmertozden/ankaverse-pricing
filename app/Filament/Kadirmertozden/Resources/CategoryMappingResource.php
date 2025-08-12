<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\CategoryMappingResource\Pages;
use App\Filament\Kadirmertozden\Resources\CategoryMappingResource\RelationManagers;
use App\Models\CategoryMapping;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryMappingResource extends Resource
{
	public static function getNavigationGroup(): ?string
{
    return 'Entegrasyon';
}

public static function getNavigationSort(): ?int
{
    return 10; // istersen 1 yap, en üstte dursun
}

    protected static ?string $model = CategoryMapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('marketplace_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('internal_category_path')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('marketplace_category_id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('marketplace_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('internal_category_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('marketplace_category_id')
                    ->searchable(),
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
            'index' => Pages\ListCategoryMappings::route('/'),
            'create' => Pages\CreateCategoryMapping::route('/create'),
            'edit' => Pages\EditCategoryMapping::route('/{record}/edit'),
        ];
    }
}
