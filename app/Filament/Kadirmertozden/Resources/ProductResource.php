<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            Forms\Components\TextInput::make('stock_code')->label('Stok Kodu')->required(),
            Forms\Components\TextInput::make('name')->label('Ürün Adı')->required(),
            Forms\Components\TextInput::make('buy_price_vat')->label('Alış Fiyatı (KDV Dahil)')->numeric()->required(),
            Forms\Components\TextInput::make('commission_rate')->label('Komisyon Oranı (%)')->numeric()->required(),
            Forms\Components\TextInput::make('width')->label('Genişlik (cm)')->numeric()->required(),
            Forms\Components\TextInput::make('length')->label('Uzunluk (cm)')->numeric()->required(),
            Forms\Components\TextInput::make('height')->label('Yükseklik (cm)')->numeric()->required(),
            Forms\Components\TextInput::make('brand')->label('Marka'),
            Forms\Components\TextInput::make('category_path')->label('Kategori Yolu'),
            Forms\Components\TextInput::make('stock_amount')->label('Stok Miktarı')->numeric()->default(0),
            Forms\Components\TextInput::make('currency_code')->label('Para Birimi'),
            Forms\Components\TextInput::make('vat_rate')->label('KDV Oranı (%)')->numeric(),
            Forms\Components\TextInput::make('gtin')->label('GTIN'),
            Forms\Components\TextInput::make('volumetric_weight')->label('Hacimsel Ağırlık')->numeric(),
            Forms\Components\Textarea::make('images')->label('Resimler (URL)')->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Açıklama')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('stock_code')->label('Stok Kodu')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ürün Adı')->searchable()->sortable()->limit(40),

                Tables\Columns\TextColumn::make('stock_amount')->label('Stok')->numeric()->sortable()->alignRight(),
                Tables\Columns\TextColumn::make('buy_price_vat')->label('Maliyet')->numeric()->sortable()->alignRight(),
                Tables\Columns\TextColumn::make('currency_code')->label('PB')->sortable(),
                Tables\Columns\TextColumn::make('sell_price')->label('Satış')->numeric()->sortable()->alignRight(),

                Tables\Columns\TextColumn::make('commission_rate')->label('Komisyon (%)')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('vat_rate')->label('KDV (%)')->numeric()->sortable(),

                Tables\Columns\TextColumn::make('created_at')->label('Oluşturma')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Güncellendi')->since()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),

                // --- Seçilileri Fiyatla ---
                BulkAction::make('repriceSelected')
                    ->label('Seçilileri Fiyatla')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        // Varsayılan profil #1'den oku (bulunamazsa ürün alanlarına düş)
                        $profile = DB::table('pricing_profiles')->where('id', 1)->first();

                        $prof_minMargin = $profile?->min_margin ?? null;            // %
                        $prof_comm      = $profile?->commission_percent ?? null;    // %
                        $prof_vat       = $profile?->vat_percent ?? null;           // %
                        $prof_round     = $profile?->rounding ?? null;              // örn: .99

                        /** @var \App\Models\Product $product */
                        foreach ($records as $product) {
                            $cost = (float) ($product->buy_price_vat ?? 0);
                            if ($cost <= 0) {
                                continue;
                            }

                            // Ürün üzerinde varsa onları, yoksa profili kullan
                            $commission = is_numeric($product->commission_rate) ? (float)$product->commission_rate
                                         : (is_numeric($prof_comm) ? (float)$prof_comm : 10.0);

                            $vat = is_numeric($product->vat_rate) ? (float)$product->vat_rate
                                   : (is_numeric($prof_vat) ? (float)$prof_vat : 20.0);

                            $minMargin = is_numeric($prof_minMargin) ? (float)$prof_minMargin : 25.0;
                            $rounding  = is_numeric($prof_round) ? (float)$prof_round : null;

                            $base     = $cost * (1 + $commission / 100);
                            $withVat  = $base * (1 + $vat / 100);
                            $withMrg  = $withVat * (1 + $minMargin / 100);
                            $price    = $withMrg;

                            if ($rounding !== null) {
                                $floor = floor($price);
                                $candidate = $floor + $rounding; // .99 gibi
                                $price = ($candidate < $withMrg) ? ($floor + 1 + $rounding) : $candidate;
                            }

                            $product->sell_price = round($price, 2);
                            $product->save();
                        }
                    })
                    ->icon('heroicon-o-banknotes')
                    ->color('success'),
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
