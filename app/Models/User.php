<?php

namespace App\Models;

use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
// (gerekirse) use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    // use Notifiable; // varsa kalsın

    public function canAccessPanel(Panel $panel): bool
    {
        // Şimdilik tüm giriş yapmış kullanıcılar panele girebilsin:
        return true;

        // İlerde rol/e-posta kontrolü yapacaksan:
        // return $this->is_admin === 1;
        // veya
        // return in_array($this->email, ['seninmail@...']);
    }
}
