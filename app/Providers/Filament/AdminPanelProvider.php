<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ScheduleDashboard;
use App\Filament\Resources\Outstandings\OutstandingResource;
use Awcodes\QuickCreate\QuickCreatePlugin;
use BezhanSalleh\FilamentExceptions\FilamentExceptionsPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Devonab\FilamentEasyFooter\EasyFooterPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inerba\DbConfig\DbConfig;
use Jacobtims\FilamentLogger\FilamentLoggerPlugin;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use Leek\FilamentDiceBear\DiceBearPlugin;
use Leek\FilamentDiceBear\DiceBearProvider;
use Leek\FilamentDiceBear\Enums\DiceBearStyle;
use SpyApp\ThemeEdinburgh\ThemeEdinburghPlugin;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->login()
            ->colors([
                'primary' => Color::Taupe,
            ])
            ->brandName(DbConfig::get("general.brand_name"))
            ->databaseNotifications()
            ->databaseNotificationsPolling('20s')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->breadcrumbs(false)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Work'),
                    // ->icon(Heroicon::OutlinedShoppingCart),
                NavigationGroup::make()
                    ->label('Main'),
                NavigationGroup::make()
                    ->label('Support Reports')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Borrow Reports')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Administration')
                    ->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                ScheduleDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
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
            ->defaultAvatarProvider(DiceBearProvider::class)
            ->plugins([
                DiceBearPlugin::make()
                    ->style(DiceBearStyle::Adventurer)
                    ->seedUsing(fn($record) => $record->name)
                    ->cache(true)
                    ->disk('public')
                    ->cachePath('avatars/dicebear'),
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 4,
                        'lg' => 6
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
                ThemeEdinburghPlugin::make(),
                FilamentApexChartsPlugin::make(),
                FilamentBackgroundsPlugin::make()->imageProvider(
                    MyImages::make()
                        ->directory('images/backgrounds')
                ),
                FilamentLoggerPlugin::make(),
                BreezyCore::make()
                    ->myProfile(),
                QuickCreatePlugin::make()
                    ->includes([
                        OutstandingResource::class,
                    ]),
                EasyFooterPlugin::make()
                    ->withFooterPosition('footer')
                    ->withSentence('Made with ☕ & 🚬 by @bugihermansyah  | v2026.2.0.2 |')
                    ->withLoadTime(),
                FilamentExceptionsPlugin::make(),
            ])
            ->sidebarWidth('14rem')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->maxContentWidth(Width::Full)
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
