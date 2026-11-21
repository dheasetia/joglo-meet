<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use App\Filament\Widgets\LatestMeetings;
use App\Filament\Widgets\MasterDataStats;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel /admin: data master (pengguna, unit, jenis rapat). Hanya untuk super admin & pimpinan.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Manajemen Rapat · Data Master')
            ->brandLogo(fn () => new HtmlString(
                '<span style="display:flex;align-items:center;gap:.6rem">'
                .'<img src="'.asset('images/logo-joglo-quran-192.png').'" alt="" style="height:2.25rem;width:2.25rem;border-radius:9999px">'
                .'<span style="font-weight:700;line-height:1.1">Manajemen Rapat<br><span style="font-size:.75rem;font-weight:500;opacity:.7">Data Master</span></span>'
                .'</span>'
            ))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon-32.png'))
            // Warna identitas jogloquran.com
            ->colors([
                'primary' => Color::hex('#ae152d'),
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([Dashboard::class])
            // Isi halaman Dasbor /admin
            ->widgets([
                AccountWidget::class,
                MasterDataStats::class,
                LatestMeetings::class,
            ])
            ->navigationItems([
                NavigationItem::make('Kembali ke Aplikasi Rapat')
                    ->url('/')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->sort(-10),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
