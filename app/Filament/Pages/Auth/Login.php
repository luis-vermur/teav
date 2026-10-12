<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Pantalla de acceso de COMERCIALIZADORA TEAV.
 *
 * Extiende la de Filament en lugar de reemplazarla: toda la lógica de
 * autenticación, límite de intentos y doble factor sigue viniendo de la
 * clase base. Aquí sólo se cambia lo que se lee en pantalla.
 */
class Login extends BaseLogin
{
    public function getTitle(): string | Htmlable
    {
        return 'Acceso · TEAV';
    }

    public function getHeading(): string | Htmlable | null
    {
        // Durante el doble factor la clase base cambia el encabezado;
        // se respeta ese comportamiento en lugar de pisarlo.
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return new HtmlString(
            '<span class="teav-login-kicker">Sistema de arrendamiento</span>'
            . '<span class="teav-login-title">Bienvenido</span>'
        );
    }

    public function getSubheading(): string | Htmlable | null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return new HtmlString(
            '<span class="teav-login-sub">Entra con tu cuenta para continuar.</span>'
        );
    }
}
