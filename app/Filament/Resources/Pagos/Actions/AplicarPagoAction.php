<?php

namespace App\Filament\Resources\Pagos\Actions;

use App\Models\Pago;
use App\Services\PagoService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Acredita un pago al contrato: lo reparte entre recargos y exhibiciones
 * pendientes, de lo más antiguo a lo más nuevo.
 *
 * Existe porque marcar "aplicado" desde el formulario sólo escribía el
 * estatus, sin mover un peso. Ahora la única vía de aplicar es ésta, que
 * pasa por PagoService::aplicar().
 */
class AplicarPagoAction
{
    public static function make(string $nombre = 'aplicar'): Action
    {
        return Action::make($nombre)
            ->label('Aplicar al contrato')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            // Sólo tiene sentido si queda dinero sin acreditar.
            ->visible(fn (Pago $record) => ! in_array($record->estatus, ['reversado', 'rechazado'], true)
                && $record->montoDisponible() > 0)
            ->requiresConfirmation()
            ->modalHeading('Aplicar el pago al contrato')
            ->modalDescription(fn (Pago $record) => new HtmlString(self::previsualizar($record)))
            ->modalSubmitActionLabel('Sí, aplicar')
            ->action(function (Pago $record) {
                $antes = $record->montoDisponible();

                app(PagoService::class)->aplicar($record);
                $record->refresh();

                $aplicado = round($antes - $record->montoDisponible(), 2);

                Notification::make()
                    ->title('Pago aplicado')
                    ->body('Se acreditaron $' . number_format($aplicado, 2)
                        . ($record->saldo_a_favor > 0
                            ? '. Quedaron $' . number_format((float) $record->saldo_a_favor, 2) . ' a favor del cliente.'
                            : '.'))
                    ->success()
                    ->send();
            });
    }

    /** Qué va a cubrir el pago, antes de confirmarlo. */
    protected static function previsualizar(Pago $record): string
    {
        $disponible = $record->montoDisponible();
        $contrato   = $record->contrato;

        if (! $contrato) {
            return 'Este pago no está ligado a un contrato, no hay a qué aplicarlo.';
        }

        $recargos = (float) $contrato->penalizaciones()
            ->whereIn('estatus', ['pendiente', 'parcial'])
            ->sum('saldo');

        $exhibiciones = (float) $contrato->pagosProgramados()
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
            ->sum('saldo');

        $f = fn ($n) => '$' . number_format((float) $n, 2);

        return "<div style='font-size:14px;line-height:1.9'>"
            . 'Se repartirán <b>' . $f($disponible) . '</b> del pago '
            . "<b>{$record->folio}</b> en el contrato <b>{$contrato->folio}</b>:<br>"
            . '• Primero los recargos pendientes (' . $f($recargos) . ')<br>'
            . '• Después las exhibiciones vencidas, de la más antigua (' . $f($exhibiciones) . ')<br>'
            . '<span style="color:#6b7280">Lo que sobre queda como saldo a favor.</span>'
            . '</div>';
    }
}
