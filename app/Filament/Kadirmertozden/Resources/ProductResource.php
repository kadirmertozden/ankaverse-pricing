<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Ürünler';
    protected static ?string $pluralLabel = 'Ürünler';
    protected static ?string $modelLabel = 'Ürün';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('stock_code')->label('Stok Kodu')->required()->maxLength(100),
            TextInput::make('name')->label('Ad')->required()->columnSpanFull(),

            TextInput::make('buy_price_vat')->label('Alış (KDV d.)')
                ->numeric()->step('0.01'),

            TextInput::make('commission_rate')->label('Komisyon %')
                ->numeric()->step('0.01'),

            Select::make('currency_code')->label('Para Birimi')
                ->options(['TL'=>'TL','USD'=>'USD','EUR'=>'EUR'])->native(false),

            TextInput::make('vat_rate')->label('KDV Oranı')->numeric()->default('20.00'),

            TextInput::make('stock_amount')->label('Stok')
                ->numeric()->default(0),

            TextInput::make('brand')->label('Marka'),
            TextInput::make('category_path')->label('Kategori')->columnSpanFull(),
            TextInput::make('gtin')->label('GTIN'),

            TextInput::make('width')->label('En')->numeric()->step('0.01'),
            TextInput::make('length')->label('Boy')->numeric()->step('0.01'),
            TextInput::make('height')->label('Yükseklik')->numeric()->step('0.01'),
            TextInput::make('volumetric_weight')->label('Hacim Ağırlığı')->numeric()->step('0.01'),

            // === GÖRSELLER ===
            FileUpload::make('images')
                ->label('Görseller')
                ->directory('products')     // storage/app/public/products
                ->disk('public')
                ->image()
                ->multiple()
                ->reorderable()
                ->downloadable()
                ->openable()
                ->panelLayout('grid')       // hoş bir grid
                ->columnSpanFull(),

            Textarea::make('description')->label('Açıklama')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            // İlk görsel önizleme
            ImageColumn::make('images')
                ->label('')
                ->circular()
                ->getStateUsing(function ($record) {
                    $imgs = $record->images ?? [];
                    return is_array($imgs) && count($imgs) ? $imgs[0] : null;
                })
                ->height(40)
                ->toggleable(),

            TextColumn::make('stock_code')->label('Stok')->searchable(),
            TextColumn::make('name')->label('Ad')->limit(40)->searchable(),

            TextColumn::make('buy_price_vat')->label('Alış')
                ->money(fn($record) => match ($record->currency_code) {
                    'USD' => 'USD',
                    'EUR' => 'EUR',
                    default => 'TRY',
                }, true)
                ->sortable(),

            TextColumn::make('stock_amount')->label('Stok')->sortable(),
            TextColumn::make('currency_code')->label('PB'),
            TextColumn::make('updated_at')->label('Güncellendi')->dateTime('d.m.Y H:i')->sortable(),
        ])
        ->filters([])
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
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

