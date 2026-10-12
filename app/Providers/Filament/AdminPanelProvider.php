<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)

            /**
             * Perfil y recuperación de contraseña.
             *
             * Sin esto, un usuario que olvidaba su clave sólo podía
             * recuperarla entrando por tinker.
             *
             * OJO: el correo de recuperación necesita MAIL_MAILER real.
             * Hoy está en 'log', así que el enlace se escribe en
             * storage/logs/laravel.log en vez de enviarse.
             */
            ->profile()
            ->passwordReset()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])

            /**
             * Filament deja los relation managers en SÓLO LECTURA cuando
             * viven en una página "Ver". Eso escondía el botón de subir
             * documentos: podías verlos desde la ficha del cliente pero no
             * agregar uno, y había que irse a la pantalla de edición.
             *
             * Aquí las fichas son el lugar natural de trabajo —se consulta
             * el expediente y se sube lo que falta en el mismo sitio—, así
             * que se desactiva ese comportamiento. La autorización real la
             * siguen resolviendo las policies de cada modelo.
             */
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)

            // ---------- Identidad ----------
            ->brandName('COMERCIALIZADORA TEAV')
            ->brandLogo(asset('images/logo-teav.png'))
            ->darkModeBrandLogo(asset('images/logo-teav-blanco.png'))
            ->brandLogoHeight('2.4rem')
            ->favicon(asset('images/favicon.svg'))

            // ---------- Tema ----------
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Inter')
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => [
                    50  => '250, 248, 242',
                    100 => '243, 237, 222',
                    200 => '233, 220, 187',
                    300 => '217, 183, 90',
                    400 => '196, 155, 62',
                    500 => '184, 145, 47',
                    600 => '138, 106, 30',
                    700 => '110, 84, 24',
                    800 => '23, 23, 28',
                    900 => '16, 16, 20',
                    950 => '8, 8, 10',
                ],
                // Morado para los cargos de gastos iniciales en cobranza.
                // Filament sólo resuelve los colores registrados aquí: sin
                // esta línea, ->color('purple') no pinta nada.
                'purple' => Color::Purple,
                'gray' => [
                    50  => '245, 244, 241',
                    100 => '228, 226, 221',
                    200 => '200, 198, 193',
                    300 => '152, 152, 159',
                    400 => '107, 107, 112',
                    500 => '80, 80, 86',
                    600 => '58, 58, 65',
                    700 => '35, 35, 41',
                    800 => '23, 23, 28',
                    900 => '16, 16, 20',
                    950 => '8, 8, 10',
                ],
            ])

            // ---------- Contenido ----------
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Operación'),
                NavigationGroup::make('Flota'),
                NavigationGroup::make('Directorio'),
                NavigationGroup::make('Configuración')->collapsed(),
            ])

            // ---------- Middleware ----------
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
            ])

            // Pie discreto bajo el formulario de acceso: quién es el dueño
            // del sistema y a quién llamar si alguien no puede entrar.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): HtmlString => new HtmlString(
                    '<p class="teav-login-pie">'
                    . 'Acceso exclusivo para personal de COMERCIALIZADORA TEAV.<br>'
                    . '¿Problemas para entrar? Contacta a tu administrador.'
                    . '</p>'
                ),
            );
    }
}