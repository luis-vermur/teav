<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Services\PagoService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Pagos recibidos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('folio')
            ->columns([
                TextColumn::make('folio')->searchable(),

                TextColumn::make('fecha_pago')->label('Fecha')->date('d/M/Y')->sortable(),

                TextColumn::make('monto')->money('MXN')->weight('bold'),

                TextColumn::make('metodo_pago')
                    ->label('Método')
                    ->formatStateUsing(fn(string $state) => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('referencia')->toggleable(),

                TextColumn::make('saldo_a_favor')
                    ->label('A favor')
                    ->money('MXN')
                    ->color('warning')
                    ->placeholder('—'),

                TextColumn::make('estatus')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'aplicado' => 'success',
                        'reversado', 'rechazado' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('registradoPor.name')->label('Capturó')->toggleable(),
            ])
            ->recordActions([
                Action::make('reversar')
                    ->label('Reversar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn($record) => $record->estatus === 'aplicado'
                        && Gate::allows('reversar_pago'))
                    ->requiresConfirmation()
                    ->modalDescription('Se deshará la aplicación y las exhibiciones volverán a quedar pendientes.')
                    ->schema([
                        Textarea::make('motivo')->label('Motivo del reverso')->required()->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(PagoService::class)->reversar($record, $data['motivo']);

                        Notification::make()->title('Pago reversado')->warning()->send();
                    }),
            ])
            ->defaultSort('fecha_pago', 'desc');
    }
}
