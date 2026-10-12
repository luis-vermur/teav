<?php

namespace App\Filament\Resources\Contratos\Actions;

use App\Models\Contrato;
use App\Services\ContratoService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * Activa el contrato: genera el calendario de pagos, registra el depósito
 * y marca la unidad como arrendada.
 *
 * Se define una sola vez y se usa en el listado y en la ficha. Antes sólo
 * existía en el listado, así que quien abría el contrato para revisarlo no
 * tenía manera de activarlo desde ahí.
 *
 * Cuando algo lo impide, el botón se muestra deshabilitado y dice QUÉ
 * falta, en lugar de dejar que el usuario lo pulse para recibir un error.
 */
class ActivarContratoAction
{
    public static function make(string $nombre = 'activar'): Action
    {
        return Action::make($nombre)
            ->label('Activar contrato')
            ->icon('heroicon-m-play')
            ->color('success')
            ->visible(fn (Contrato $record) => in_array($record->estatus, ['borrador', 'por_firmar'], true)
                && Gate::allows('activar_contrato'))
            ->disabled(fn (Contrato $record) => ! $record->puedeActivarse())
            ->tooltip(fn (Contrato $record) => $record->puedeActivarse()
                ? null
                : 'Falta: ' . implode(' · ', $record->motivosParaNoActivar()))
            ->requiresConfirmation()
            ->modalHeading('Activar contrato')
            ->modalDescription(fn (Contrato $record) => new HtmlString(
                'Se generará el calendario de <b>' . $record->plazo_meses . ' rentas</b>'
                . self::extras($record)
                . ', se registrará el depósito en garantía y el vehículo quedará como arrendado.'
            ))
            ->modalSubmitActionLabel('Sí, activar')
            ->action(function (Contrato $record) {
                try {
                    app(ContratoService::class)->activar($record);

                    $cargos = $record->pagosProgramados()->count();

                    Notification::make()
                        ->title('Contrato activado')
                        ->body("Se generaron {$cargos} cargos en el calendario de pagos.")
                        ->success()
                        ->send();
                } catch (\RuntimeException $e) {
                    Notification::make()
                        ->title('No se pudo activar')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /** Qué otros cargos entran al calendario además de las rentas. */
    protected static function extras(Contrato $c): string
    {
        $extras = [];

        if ($c->cobra_seguro && (float) $c->monto_seguro_anual > 0) {
            $extras[] = (int) ceil($c->plazo_meses / 12) . ' cargos de seguro';
        }

        if ((float) $c->gastos_iniciales > 0) {
            $extras[] = 'los gastos iniciales';
        }

        return $extras ? ', más ' . implode(' y ', $extras) : '';
    }
}
