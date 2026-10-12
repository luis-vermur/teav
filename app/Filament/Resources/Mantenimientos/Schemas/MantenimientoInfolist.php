<?php

namespace App\Filament\Resources\Mantenimientos\Schemas;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Proveedors\ProveedorResource;
use App\Filament\Resources\Unidads\UnidadResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del servicio.
 *
 * La pregunta que abre todo es quién pagó: si fue el cliente, esto es
 * bitácora del vehículo; si fue la arrendadora, es un gasto que pesa. Por
 * eso el responsable va primero y con color.
 */
class MantenimientoInfolist
{
    private const RESPONSABLE = [
        'arrendatario' => ['El cliente', 'gray'],
        'arrendadora'  => ['La arrendadora', 'warning'],
        'seguro'       => ['El seguro', 'info'],
        'garantia'     => ['La garantía', 'success'],
    ];

    private const ESTATUS = [
        'programado' => ['Programado', 'warning'],
        'en_proceso' => ['En proceso', 'info'],
        'completado' => ['Completado', 'success'],
        'cancelado'  => ['Cancelado', 'gray'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────── RESUMEN ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('responsable_costo')
                        ->label('¿Quién pagó?')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::RESPONSABLE[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::RESPONSABLE[$state][1] ?? 'gray'),

                    TextEntry::make('costo_total')
                        ->label('Costo total')
                        ->state(fn ($record) => $record->costo_total)
                        ->money('MXN')
                        ->weight('bold')
                        ->helperText(fn ($record) => 'Sin IVA: $' . number_format((float) $record->costo, 2)),

                    TextEntry::make('kilometraje')
                        ->label('Kilómetros al momento')
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' km')
                        ->placeholder('—'),

                    /**
                     * Dinero de la arrendadora en la calle. La ausencia de
                     * recobro pendiente también informa, así que se muestra
                     * en ambos casos.
                     */
                    TextEntry::make('recobro')
                        ->label('Recobro al cliente')
                        ->state(fn ($record) => match (true) {
                            ! $record->se_recobra          => 'No aplica',
                            $record->pendienteDeRecobro()  => 'Pendiente de cobro',
                            default                        => 'Ya recobrado',
                        })
                        ->badge()
                        ->color(fn ($record) => match (true) {
                            ! $record->se_recobra         => 'gray',
                            $record->pendienteDeRecobro() => 'danger',
                            default                       => 'success',
                        }),
                ])
                ->columns(4),

            // ─────────────── DETALLE ───────────────
            Section::make('Detalle del servicio')
                ->schema([
                    TextEntry::make('tipo.nombre')
                        ->label('Tipo de servicio')
                        ->weight('bold'),

                    TextEntry::make('fecha')
                        ->label('Fecha')
                        ->date('d/M/Y'),

                    TextEntry::make('estatus')
                        ->label('Situación')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::ESTATUS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::ESTATUS[$state][1] ?? 'gray'),

                    TextEntry::make('proveedor.nombre')
                        ->label('Taller')
                        ->placeholder('Sin taller registrado')
                        ->url(fn ($record) => $record->proveedor
                            ? ProveedorResource::getUrl('view', ['record' => $record->proveedor])
                            : null)
                        ->color(fn ($record) => $record->proveedor ? 'primary' : 'gray'),

                    TextEntry::make('folio_taller')
                        ->label('Folio del taller')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('descripcion')
                        ->label('¿Qué se hizo?')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),

                    TextEntry::make('refacciones')
                        ->label('Refacciones')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(4),

            // ─────────────── PRÓXIMO SERVICIO ───────────────
            Section::make('Próximo servicio')
                ->visible(fn ($record) => filled($record->proximo_fecha) || filled($record->proximo_kilometraje))
                ->schema([
                    TextEntry::make('proximo_fecha')
                        ->label('Próxima fecha')
                        ->placeholder('Sin programar')
                        ->formatStateUsing(fn ($state) => blank($state)
                            ? null
                            : ($state->isPast()
                                ? $state->format('d/M/Y') . ' — vencido'
                                : $state->format('d/M/Y')))
                        ->color(fn ($state) => filled($state) && $state->isPast() ? 'danger' : null)
                        ->weight(fn ($state) => filled($state) && $state->isPast() ? 'bold' : null),

                    TextEntry::make('proximo_kilometraje')
                        ->label('O a los')
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' km')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(2),

            // ─────────────── RELACIONADOS ───────────────
            Section::make('Vehículo y contrato')
                ->collapsed()
                ->schema([
                    TextEntry::make('unidad.descripcion')
                        ->label('Vehículo')
                        ->weight('bold')
                        ->helperText(fn ($record) => $record->unidad?->codigo_interno)
                        ->url(fn ($record) => $record->unidad
                            ? UnidadResource::getUrl('view', ['record' => $record->unidad])
                            : null)
                        ->color('primary'),

                    TextEntry::make('contrato.folio')
                        ->label('Contrato')
                        ->placeholder('Sin contrato asociado')
                        ->url(fn ($record) => $record->contrato
                            ? ContratoResource::getUrl('view', ['record' => $record->contrato])
                            : null)
                        ->color(fn ($record) => $record->contrato ? 'primary' : 'gray'),
                ])
                ->columns(2),

            // ─────────────── PIE ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Registrado el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),
                ])
                ->columns(2),
        ]);
    }
}
