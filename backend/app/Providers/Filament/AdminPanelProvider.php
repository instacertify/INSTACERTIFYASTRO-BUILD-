<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Http\Controllers\Admin\JobApplicationCvController;
use App\Models\Setting;
use App\Support\Images;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            ->passwordReset(RequestPasswordReset::class)
            ->navigationItems([
                NavigationItem::make('Change password')
                    ->group('Settings')
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn () => filament()->getProfileUrl())
                    ->isActiveWhen(fn () => request()->routeIs('filament.admin.auth.profile'))
                    ->sort(100),
            ])
            ->brandName('Instacertify')
            ->brandLogo(fn () => self::uploadedLogo() ?? asset('images/brand/logo.png'))
            ->darkModeBrandLogo(fn () => self::uploadedLogo() ?? asset('images/brand/logo-white.png'))
            ->brandLogoHeight(fn () => request()->routeIs('filament.admin.auth.*') ? '3rem' : '2.25rem')
            ->favicon(fn () => Images::url(Setting::get('site', [])['favicon'] ?? null) ?? asset('favicon.png'))
            ->font('Inter')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.admin-highlights'))
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => view('filament.lead-email-alert'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0&display=block">'))
            ->navigationGroups(['CRM', 'Marketing', 'Careers', 'Website', 'Resources', 'Standards library', 'Settings'])
            ->authenticatedRoutes(function () {
                Route::get('job-applications/{application}/cv', JobApplicationCvController::class)->name('job-applications.cv');
            })
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /** The "Website logo" uploaded under Site settings, so the admin matches the website. */
    private static function uploadedLogo(): ?string
    {
        return Images::url(Setting::get('site', [])['logo'] ?? null);
    }
}
