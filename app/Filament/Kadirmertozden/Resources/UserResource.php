<?php

namespace App\Filament\Kadirmertozden\Resources;

use App\Filament\Kadirmertozden\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Kullanıcılar';
    protected static ?string $pluralLabel = 'Kullanıcılar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Ad Soyad')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label('E‑posta')
                ->email()
                ->required()
                ->maxLength(255)
                // Filament v3: edit ekranında kaydı yok sayarak unique
                ->unique(ignoreRecord: true),

            Forms\Components\DateTimePicker::make('email_verified_at')
                ->label('E‑posta Doğrulandı')
                ->native(false)
                ->seconds(false),

            Forms\Components\TextInput::make('password')
                ->label('Şifre')
                ->password()
                // Create ekranında zorunlu, Edit’te opsiyonel
                ->required(fn (string $context) => $context === 'create')
                // Boşsa veritabanına yazma; doluysa hash’le
                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->revealable(),

            // Opsiyonel: sadece adminler görsün
            Forms\Components\Toggle::make('is_admin')
                ->label('Yönetici (Admin)')
                ->default(false)
                ->visible(fn () => auth()->user()?->is_admin === true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E‑posta')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label('Doğrulandı')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                // Opsiyonel: tek tık şifre sıfırlama
                Tables\Actions\Action::make('resetPassword')
                    ->label('Şifre Sıfırla')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $new = Str::password(16);
                        $record->password = bcrypt($new);
                        $record->save();

                        // Admin’e yeni şifreyi bildir (UI flash)
                        \Filament\Notifications\Notification::make()
                            ->title('Yeni Şifre Oluşturuldu')
                            ->body("Kullanıcı: {$record->email}\nYeni Şifre: {$new}")
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()?->is_admin === true),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
