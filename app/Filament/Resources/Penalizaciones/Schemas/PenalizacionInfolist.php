<?php

namespace App\Filament\Resources\Penalizaciones\Schemas;

use App\Filament\Resources\Contratos\ContratoResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PenalizacionInfolist
{
    private const TIPO = [
        'mora'              => 'Mora',
        'km_excedente'      => 'Km excedente',
        'daños'             => 'Daños',
        'documentos'        => 'Documentos',
        'devolucion_tardia' => 'Devolución tardía',
        'otro'              => 'Otro',
    ];

    private const ESTATUS = [
        'pendiente' => ['Pendiente', 'danger'],
        'parcial'   => ['Parcial', 'warning'],
        'pagada'    => ['Pagada', 'success'],
        'condonada' => ['Condonada', 'gray'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make()
                ->schema([
                    TextEntry::make('estatus')
                        ->label('Situación')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::ESTATUS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::ESTATUS[$state][1] ?? 'gray'),

                    TextEntry::make('monto')
                        ->label('Monto del recargo')
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('saldo')
                        ->label('Por cobrar')
                        ->money('MXN')
                        ->weight('bold')
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                    TextEntry::make('dias_atraso')
                        ->label('Días de atraso')
                        ->formatStateUsing(fn ($state) => $state > 0 ? $state . ' días' : '—'),
                ])
                ->columns(4),

            Section::make('De dónde viene')
                ->schema([
                    TextEntry::make('tipo')
                        ->label('Concepto')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state) => self::TIPO[$state] ?? ucfirst((string) $state)),

                    TextEntry::make('contrato.folio')
                        ->label('Contrato')
                        ->helperText(fn ($record) => $record->contrato?->arrendatario?->nombre_completo)
                        ->url(fn ($record) => $record->contrato
                            ? ContratoResource::getUrl('view', ['record' => $record->contrato])
                            : null)
                        ->color('primary'),

                    TextEntry::make('pagoProgramado.numero_exhibicion')
                        ->label('Exhibición que lo originó')
                        ->formatStateUsing(fn ($state) => blank($state) ? null : 'Exhibición ' . $state)
                        ->placeholder('No viene de una exhibición'),

                    TextEntry::make('fecha_generacion')
                        ->label('Se generó el')
                        ->date('d/M/Y'),

                    TextEntry::make('base_calculo')
                        ->label('Se calculó sobre')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('generada_automaticamente')
                        ->label('Origen')
                        ->state(fn ($record) => $record->generada_automaticamente
                            ? 'Generado por el sistema'
                            : 'Capturado a mano'),

                    TextEntry::make('descripcion')
                        ->label('Descripción')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(3),

            /**
             * Sólo aparece si se condonó. Es la sección más importante de
             * auditoría: dice quién perdonó el dinero y por qué.
             */
            Section::make('Condonación')
                ->visible(fn ($record) => $record->estatus === 'condonada')
                ->schema([
                    TextEntry::make('condonadaPor.name')
                        ->label('Condonado por')
                        ->weight('bold'),

                    TextEntry::make('fecha_condonacion')
                        ->label('Fecha')
                        ->date('d/M/Y'),

                    TextEntry::make('motivo_condonacion')
                        ->label('Motivo')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Section::make('Cobros aplicados')
                ->collapsed()
                ->visible(fn ($record) => $record->aplicaciones()->exists())
                ->schema([
                    TextEntry::make('aplicaciones_detalle')
                        ->label('')
                        ->state(fn ($record) => $record->aplicaciones()
                            ->with('pago')
                            ->get()
                            ->map(fn ($a) => ($a->pago?->folio ?? 'Pago')
                                . ' — $' . number_format((float) $a->monto, 2))
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
