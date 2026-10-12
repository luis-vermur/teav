<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\TextInput;

/**
 * Campo de RFC, igual en los seis formularios que lo piden.
 *
 * Existe por un detalle que parecía menor y rechazaba capturas válidas: el
 * RFC casi nunca se teclea, se pega desde una factura o desde otro sistema,
 * y llega con espacios alrededor. `maxLength(13)` los contaba y devolvía
 * "no debe ser mayor que 13 caracteres" sobre un RFC perfectamente correcto.
 *
 * Aquí la longitud se mide ya recortada y el valor se guarda siempre en
 * mayúsculas, para que "xaxx010101000" y "XAXX010101000" no acaben siendo
 * dos clientes distintos.
 */
class CampoRfc
{
    public static function make(string $nombre = 'rfc'): TextInput
    {
        return TextInput::make($nombre)
            ->label('RFC')
            ->placeholder('XAXX010101000')
            // Holgado a propósito: lo que valida de verdad es la regla de abajo.
            ->maxLength(20)
            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
            ->rule(static fn () => static function (string $attribute, $value, $fail): void {
                $limpio = trim((string) $value);

                if ($limpio === '') {
                    return;
                }

                if (mb_strlen($limpio) > 13) {
                    $fail('El RFC no puede tener más de 13 caracteres.');
                }
            })
            ->dehydrateStateUsing(fn (?string $state) => filled($state)
                ? mb_strtoupper(trim($state))
                : null);
    }
}
