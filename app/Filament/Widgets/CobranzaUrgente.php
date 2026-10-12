<?php

namespace App\Filament\Widgets;

use App\Models\PagoProgramado;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CobranzaUrgente extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Cobranza vencida';

    protected int|string|array $columnSpan = 'full';

    /**
     * Adeudos por cobrar, con nombre de cliente y montos: lo necesita quien
     * hace cobranza, no cualquiera que pueda entrar al panel.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ver_pago') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PagoProgramado::query()
                    ->with('contrato.arrendatario', 'contrato.unidad.submarca.marca')
                    ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
                    ->where('fecha_limite', '<', now())
                    ->orderBy('fecha_limite')
            )
            ->columns([
                TextColumn::make('contrato.folio')->label('Contrato'),

                TextColumn::make('contrato.arrendatario.nombre_completo')
                    ->label('Arrendatario')
                    ->wrap(),

                TextColumn::make('contrato.unidad.codigo_interno')->label('Unidad'),

                TextColumn::make('numero_exhibicion')->label('Exh.'),

                TextColumn::make('fecha_limite')
                    ->label('Venció')
                    ->date('d/M/Y'),

                TextColumn::make('dias_atraso')
                    ->label('Días')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state > 60 => 'danger',
                        $state > 30 => 'warning',
                        default     => 'gray',
                    })
                    ->getStateUsing(fn ($record) => $record->diasDeAtraso()),

                TextColumn::make('saldo')
                    ->money('MXN')
                    ->weight('bold')
                    ->color('danger'),
            ])
            ->paginated([5, 10, 25])
            ->emptyStateHeading('Sin cartera vencida')
            ->emptyStateDescription('Todos los pagos están al corriente.');
    }
}
