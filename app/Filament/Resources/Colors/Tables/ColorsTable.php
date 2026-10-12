<?php

namespace App\Filament\Resources\Colors\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Catálogo de colores.
 *
 * El hex en texto ("#1B1B1B") no le dice nada a nadie; lo que sirve es ver
 * el color. Por eso la primera columna pinta una muestra real y el código
 * queda como dato secundario, copiable.
 */
class ColorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('unidades'))

            ->columns([
                /**
                 * Muestra del color. Se dibuja con estilo en línea porque es
                 * un valor de la base, no una clase de Tailwind: no existe
                 * forma de compilarlo por adelantado.
                 */
                TextColumn::make('muestra')
                    ->label('')
                    ->state('')
                    ->formatStateUsing(fn ($record) => new HtmlString(
                        '<span style="display:inline-block;width:24px;height:24px;border-radius:6px;'
                        . 'border:1px solid rgba(0,0,0,.15);background:'
                        . e($record->hex ?: 'transparent') . '"></span>'
                    ))
                    ->extraAttributes(['style' => 'width:40px']),

                TextColumn::make('nombre')
                    ->label('Color')
                    ->weight('bold')
                    ->description(fn ($record) => $record->hex)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('hex')
                    ->label('Código')
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->placeholder('Sin código')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('unidades_count')
                    ->label('En la flota')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => match (true) {
                        $state == 0 => 'Sin unidades',
                        $state == 1 => '1 unidad',
                        default     => "{$state} unidades",
                    })
                    ->sortable(),
            ])

            ->filters([
                Filter::make('sin_unidades')
                    ->label('Sin unidades en la flota')
                    ->query(fn ($query) => $query->doesntHave('unidades'))
                    ->toggle(),

                Filter::make('sin_hex')
                    ->label('Sin código de color')
                    ->query(fn ($query) => $query->whereNull('hex')->orWhere('hex', ''))
                    ->toggle(),
            ])

            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver detalle'),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->tooltip('Más acciones'),
            ])

            ->defaultSort('nombre')
            ->striped()
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin colores')
            ->emptyStateDescription('Se usan al dar de alta un vehículo, para no escribir "gris oscuro" de diez formas distintas.');
    }
}
