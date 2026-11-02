<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\EditProject;
use App\Filament\Pages\Tenancy\RegisterProject;
use App\Http\Middleware\SetLocale;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    /**
     * Emerald, one shade deeper, so buttons get white text with AA contrast.
     *
     * @return array<int, string>
     */
    private static function deepEmerald(): array
    {
        $e = Color::Emerald;

        return [
            50 => $e[50], 100 => $e[100], 200 => $e[200], 300 => $e[300], 400 => $e[500],
            500 => $e[600], 600 => $e[700], 700 => $e[800], 800 => $e[900], 900 => $e[950], 950 => $e[950],
        ];
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->brandName(fn () => __('House Cost Planner'))
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            ->tenant(Project::class)
            ->tenantRegistration(RegisterProject::class)
            ->tenantProfile(EditProject::class)
            ->colors([
                'primary' => self::deepEmerald(),
                'gray' => Color::Zinc,
            ])
            ->font(
                'Geist',
                url: 'https://fonts.googleapis.com/css2?family=Geist:wght@400..700&family=Noto+Sans+Bengali:wght@400..700&display=swap',
                provider: GoogleFontProvider::class,
            )
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.svg'))
            ->viteTheme('resources/css/filament/app/theme.css')
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::SevenExtraLarge)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->userMenuItems([
                'language' => fn (): Action => Action::make('language')
                    ->label(app()->getLocale() === 'bn' ? 'English' : 'বাংলা')
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(route('locale', app()->getLocale() === 'bn' ? 'en' : 'bn')),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => filament()->getTenant() ? Blade::render('@livewire(\App\Livewire\AddCostButton::class)') : '',
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn () => view('filament.auth-aside'),
                scopes: [Login::class, Register::class, RequestPasswordReset::class, ResetPassword::class],
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => filament()->getTenant() ? view('filament.floating-actions') : '',
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_END,
                fn () => view('filament.language-switch', ['locales' => User::LOCALES]),
            )
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
            ->middleware([SetLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
