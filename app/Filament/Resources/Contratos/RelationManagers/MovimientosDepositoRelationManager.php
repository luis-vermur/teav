<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Movimientos del depósito en garantía.
 *
 * Es dinero del cliente que la arrendadora custodia, así que su historial
 * debe poder consultarse. Sólo lectura: los movimientos los generan la
 * activación y el cierre del contrato, no se capturan a mano.
 */
class MovimientosDepositoRelationManager extends RelationManager
{
    protected static string $relationship = 'movimientosDeposito';

    protected static ?string $title = 'Depósito en garantía';

    private const TIPO = [
        'recepcion'        => ['Recepción', 'success'],
        'incremento'       => ['Incremento', 'success'],
        'retencion'        => ['Retención', 'danger'],
        'devolucion'       => ['Devolución', 'info'],
        'aplicacion_renta' => ['Aplicado a renta', 'warning'],
    ];

    /** Los genera el sistema al activar y al cerrar el contrato. */
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('motivo')
            ->columns([
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/M/Y')
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Movimiento')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::TIPO[$state][0] ?? ucfirst($state))
                    ->color(fn (string $state) => self::TIPO[$state][1] ?? 'gray'),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('MXN')
                    // Negativo es salida: se pinta en rojo con su signo.
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('saldo_resultante')
                    ->label('Saldo tras el movimiento')
                    ->money('MXN'),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('fecha', 'asc')
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('El depósito se registra al activar el contrato y se liquida al cerrarlo.');
    }
}
