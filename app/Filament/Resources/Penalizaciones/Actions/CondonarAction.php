<?php

namespace App\Filament\Resources\Penalizaciones\Actions;

use App\Models\Penalizacion;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * Perdona un recargo.
 *
 * El permiso `condonar_adeudo` existía desde el primer día del proyecto y
 * no había ninguna pantalla que lo usara: se podía otorgar el permiso pero
 * nadie podía condonar nada.
 *
 * Condonar borra dinero por cobrar, así que exige motivo escrito y queda
 * registrado quién lo hizo y cuándo.
 */
class CondonarAction
{
    public static function make(string $nombre = 'condonar'): Action
    {
        return Action::make($nombre)
            ->label('Condonar')
            ->icon('heroicon-m-hand-raised')
            ->color('warning')
            ->visible(fn (Penalizacion $record) => in_array($record->estatus, ['pendiente', 'parcial'], true)
                && Gate::allows('condonar_adeudo'))
            ->modalHeading('Condonar el recargo')
            ->modalDescription(fn (Penalizacion $record) => new HtmlString(
                'Se perdonarán <b>$' . number_format((float) $record->saldo, 2) . '</b> '
                . 'del contrato <b>' . ($record->contrato?->folio ?? '—') . '</b>. '
                . '<span style="color:#dc2626">El cliente dejará de deber ese monto.</span>'
            ))
            ->modalSubmitActionLabel('Sí, condonar')
            ->schema([
                Textarea::make('motivo')
                    ->label('¿Por qué se condona?')
                    ->rows(3)
                    ->required()
                    ->minLength(10)
                    ->helperText('Queda asentado junto con tu nombre y la fecha.')
                    ->columnSpanFull(),
            ])
            ->action(function (Penalizacion $record, array $data) {
                $perdonado = (float) $record->saldo;

                $record->condonar($data['motivo']);

                Notification::make()
                    ->title('Recargo condonado')
                    ->body('Se perdonaron $' . number_format($perdonado, 2) . '.')
                    ->success()
                    ->send();
            });
    }
}
