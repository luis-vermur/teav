<?php

namespace App\Filament\Resources\Penalizaciones\Tables;

use App\Filament\Resources\Penalizaciones\Actions\CondonarAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PenalizacionesTable
{
    private const TIPO = [
        'mora'              => ['Mora', 'danger'],
        'km_excedente'      => ['Km excedente', 'warning'],
        'daños'             => ['Daños', 'warning'],
        'documentos'        => ['Documentos', 'info'],
        'devolucion_tardia' => ['Devolución tardía', 'warning'],
        'otro'              => ['Otro', 'gray'],
    ];

    private const ESTATUS = [
        'pendiente' => ['Pendiente', 'danger'],
        'parcial'   => ['Parcial', 'warning'],
        'pagada'    => ['Pagada', 'success'],
        'condonada' => ['Condonada', 'gray'],
    ];

    public static function configure(Table $table): Table
    {
        return $table
            // Evita N+1: sin esto cada fila consulta contrato y cliente aparte.
            ->modifyQueryUsing(fn ($query) => $query->with([
                'contrato.arrendatario',
                'contrato.unidad',
                'condonadaPor',
            ]))

            ->columns([
                TextColumn::make('tipo')
                    ->label('Concepto')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::TIPO[$state][0] ?? ucfirst($state))
                    ->color(fn (string $state) => self::TIPO[$state][1] ?? 'gray')
                    ->description(fn ($record) => $record->fecha_generacion?->format('d/M/Y'))
                    ->sortable(),

                TextColumn::make('contrato.folio')
                    ->label('Contrato')
                    ->description(fn ($record) => $record->contrato?->arrendatario?->nombre_completo)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('dias_atraso')
                    ->label('Atraso')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state . ' días' : '—')
                    ->color(fn ($state) => $state > 30 ? 'danger' : null),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('MXN')
                    ->description(fn ($record) => filled($record->base_calculo)
                        ? 'sobre $' . number_format((float) $record->base_calculo, 2)
                        : null)
                    ->sortable(),

                /**
                 * COLUMNA DE DECISIÓN.
                 * Lo que falta por cobrar de este recargo: mientras sea
                 * mayor a cero, es dinero pendiente.
                 */
                TextColumn::make('saldo')
                    ->label('Por cobrar')
                    ->money('MXN')
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->placeholder('Saldado')
                    ->sortable(),

                TextColumn::make('estatus')
                    ->label('Situación')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::ESTATUS[$state][0] ?? ucfirst($state))
                    ->color(fn (string $state) => self::ESTATUS[$state][1] ?? 'gray')
                    ->description(fn ($record) => $record->estatus === 'condonada'
                        ? 'por ' . ($record->condonadaPor?->name ?? '—')
                        : null)
                    ->sortable(),
            ])

            ->filters([
                Filter::make('pendientes')
                    ->label('Sólo pendientes de cobro')
                    ->query(fn ($query) => $query->whereIn('estatus', ['pendiente', 'parcial']))
                    ->toggle(),

                Filter::make('condonadas')
                    ->label('Sólo condonadas')
                    ->query(fn ($query) => $query->where('estatus', 'condonada'))
                    ->toggle(),

                SelectFilter::make('tipo')
                    ->label('Concepto')
                    ->options(array_map(fn ($t) => $t[0], self::TIPO))
                    ->multiple(),
            ])
            ->filtersFormColumns(3)

            ->recordActions([
                CondonarAction::make()->button()->size('sm'),

                ActionGroup::make([
                    ViewAction::make()->label('Ver detalle'),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->tooltip('Más acciones'),
            ])

            ->defaultSort('fecha_generacion', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin recargos')
            ->emptyStateDescription('Se generan solos cuando una exhibición pasa su fecha límite, con el comando diario de cobranza.');
    }
}
