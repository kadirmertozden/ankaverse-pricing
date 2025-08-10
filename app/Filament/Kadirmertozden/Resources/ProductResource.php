<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Ürünler';
    protected static ?string $pluralLabel = 'Ürünler';
    protected static ?string $modelLabel = 'Ürün';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('stock_code')
                    ->label('Stok Kodu')
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Ürün Adı')
                    ->required(),

                Forms\Components\TextInput::make('buy_price_vat')
                    ->label('Alış Fiyatı (KDV Dahil)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('commission_rate')
                    ->label('Komisyon Oranı (%)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('width')
                    ->label('Genişlik (cm)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('length')
                    ->label('Uzunluk (cm)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('height')
                    ->label('Yükseklik (cm)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('brand')
                    ->label('Marka'),

                Forms\Components\TextInput::make('category_path')
                    ->label('Kategori Yolu'),

                Forms\Components\TextInput::make('stock_amount')
                    ->label('Stok Miktarı')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('currency_code')
                    ->label('Para Birimi'),

                Forms\Components\TextInput::make('vat_rate')
                    ->label('KDV Oranı (%)')
                    ->numeric(),

                Forms\Components\TextInput::make('gtin')
                    ->label('GTIN'),

                Forms\Components\TextInput::make('volumetric_weight')
                    ->label('Hacimsel Ağırlık')
                    ->numeric(),

                Forms\Components\Textarea::make('images')
                    ->label('Resimler (URL)')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->label('Açıklama')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('stock_code')->label('Stok Kodu')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Ürün Adı')->searchable(),
                Tables\Columns\TextColumn::make('buy_price_vat')->label('Alış Fiyatı')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('commission_rate')->label('Komisyon Oranı')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('width')->label('Genişlik')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('length')->label('Uzunluk')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('height')->label('Yükseklik')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('stock_amount')->label('Stok')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('currency_code')->label('Para Birimi')->searchable(),
                Tables\Columns\TextColumn::make('vat_rate')->label('KDV')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Oluşturma Tarihi')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
