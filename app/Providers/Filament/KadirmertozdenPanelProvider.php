<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class KadirmertozdenPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('kadirmertozden')
            ->path('admin')
            ->brandName('Ankaverse Admin')
            ->favicon(asset('favicon.ico'))
            ->login() // Filament default login
            ->discoverResources(
                in: app_path('Filament/Kadirmertozden/Resources'),
                for: 'App\\Filament\\Kadirmertozden\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Kadirmertozden/Pages'),
                for: 'App\\Filament\\Kadirmertozden\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Kadirmertozden/Widgets'),
                for: 'App\\Filament\\Kadirmertozden\\Widgets'
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
