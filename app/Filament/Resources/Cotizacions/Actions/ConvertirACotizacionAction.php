<?php

namespace App\Filament\Resources\Cotizacions\Actions;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Cotizacion;
use App\Services\CotizacionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Convierte una cotización aceptada en contrato.
 *
 * Se define una sola vez y se reutiliza en la tabla y en la ficha.
 *
 * A diferencia de antes, el botón NO se esconde cuando la cotización aún
 * no califica: se muestra deshabilitado y dice qué falta. Esconderlo hacía
 * que la gente no supiera que la función existía.
 */
class ConvertirACotizacionAction
{
    public static function make(string $nombre = 'convertir'): Action
    {
        return Action::make($nombre)
            ->label('Convertir a contrato')
            ->icon('heroicon-o-document-plus')
            ->color('success')
            // Ya convertida: no hay nada que hacer aquí.
            ->visible(fn (Cotizacion $record) => $record->contrato_id === null)
            ->disabled(fn (Cotizacion $record) => ! $record->puedeConvertirse())
            ->tooltip(fn (Cotizacion $record) => $record->puedeConvertirse()
                ? null
                : 'Falta: ' . implode(' · ', $record->motivosParaNoConvertirse()))
            ->modalHeading('Convertir cotización en contrato')
            ->modalDescription('Se creará un contrato en borrador con los términos cotizados. Podrás revisarlo antes de activarlo.')
            ->modalSubmitActionLabel('Crear contrato')
            ->schema([
                // Se muestra qué se va a heredar, para que nadie tenga que
                // adivinar si el seguro y los gastos se copian o no.
                Placeholder::make('resumen')
                    ->label('')
                    ->content(fn (Cotizacion $record) => new HtmlString(self::resumen($record)))
                    ->columnSpanFull(),

                DatePicker::make('fecha_inicio')
                    ->label('Inicio del contrato')
                    ->default(fn (Cotizacion $record) => $record->fecha_inicio_estimada ?? now()->addDay())
                    ->required(),

                TextInput::make('dia_corte')
                    ->label('Día de corte')
                    ->numeric()->minValue(1)->maxValue(31)
                    ->default(fn (Cotizacion $record) => $record->dia_corte ?? 1)
                    ->required(),

                TextInput::make('dias_gracia')
                    ->label('Días de tolerancia')
                    ->numeric()->minValue(0)
                    ->default(5)
                    ->helperText('Antes de cobrar recargo'),

                Select::make('tipo_penalizacion')
                    ->label('Recargo por atraso')
                    ->options([
                        'ninguna'            => 'Sin recargo',
                        'porcentaje_mensual' => 'Porcentaje mensual sobre lo vencido',
                        'monto_fijo_diario'  => 'Cantidad fija por día',
                        'porcentaje_unico'   => 'Porcentaje único',
                    ])
                    ->default('porcentaje_mensual')
                    ->required(),

                TextInput::make('valor_penalizacion')
                    ->label('Valor del recargo')
                    ->numeric()->step('0.0001')
                    ->default(0.05)
                    ->helperText('Fracción: 0.05 = 5%'),
            ])
            ->action(function (Cotizacion $record, array $data) {
                try {
                    $contrato = app(CotizacionService::class)->convertirAContrato($record, $data);

                    Notification::make()
                        ->title("Contrato {$contrato->folio} creado")
                        ->body('Queda en borrador. Revísalo y actívalo cuando esté firmado.')
                        ->success()
                        // En Filament 5 las notificaciones usan la misma
                        // Filament\Actions\Action que el resto del panel.
                        ->actions([
                            Action::make('ver')
                                ->label('Ver contrato')
                                ->url(ContratoResource::getUrl('view', ['record' => $contrato])),
                        ])
                        ->persistent()
                        ->send();
                } catch (\RuntimeException $e) {
                    Notification::make()
                        ->title('No se pudo convertir')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /** Lo que el contrato va a heredar de la cotización. */
    protected static function resumen(Cotizacion $c): string
    {
        $f = fn ($n) => '$' . number_format((float) $n, 2);

        $lineas = [
            'Vehículo: <b>' . e($c->vehiculo) . '</b>',
            'Cliente: <b>' . e($c->arrendatario?->nombre_completo ?? '—') . '</b>',
            'Renta mensual: <b>' . $f($c->renta_con_iva) . '</b> por <b>' . $c->plazo_meses . ' meses</b>',
        ];

        if ($c->cobra_seguro && (float) $c->monto_seguro_anual > 0) {
            $lineas[] = 'Seguro anual al cliente: <b>' . $f($c->monto_seguro_anual) . '</b>';
        }

        if ((float) $c->gastos_iniciales > 0) {
            $lineas[] = ($c->concepto_gastos_iniciales ?: 'Gastos iniciales')
                . ': <b>' . $f($c->gastos_iniciales) . '</b>';
        }

        if ((float) $c->deposito_garantia > 0) {
            $lineas[] = 'Depósito en garantía: <b>' . $f($c->deposito_garantia) . '</b>';
        }

        return "<div style='font-size:14px;line-height:1.9'>"
            . "<div style='font-weight:600;margin-bottom:4px'>El contrato heredará:</div>"
            . implode('<br>', $lineas)
            . '</div>';
    }
}
