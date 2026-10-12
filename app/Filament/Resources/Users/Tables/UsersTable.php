<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Usuarios del panel.
 *
 * Lo que importa de un usuario no es cuándo se creó, sino qué puede hacer:
 * el rol es la columna central. Un usuario sin rol no puede entrar a nada,
 * así que se marca en rojo en vez de dejarlo en blanco.
 */
class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))

            ->columns([
                TextColumn::make('name')
                    ->label('Usuario')
                    ->weight('bold')
                    ->description(fn ($record) => $record->email)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state)))
                    ->color(fn ($state) => match ($state) {
                        'administrador' => 'danger',
                        'gerente'       => 'warning',
                        'cobranza'      => 'purple',
                        'flotilla'      => 'info',
                        'operador'      => 'info',
                        'consulta'      => 'gray',
                        default         => 'gray',
                    })
                    ->placeholder('Sin rol asignado'),

                TextColumn::make('email_verified_at')
                    ->label('Correo')
                    ->badge()
                    ->state(fn ($record) => $record->email_verified_at ? 'Verificado' : 'Sin verificar')
                    ->color(fn ($record) => $record->email_verified_at ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dado de alta')
                    ->date('d/M/Y')
                    ->color('gray')
                    ->sortable(),
            ])

            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                Filter::make('sin_rol')
                    ->label('Sin rol asignado')
                    ->query(fn ($query) => $query->doesntHave('roles'))
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

            ->defaultSort('name')
            ->striped()
            ->paginated([25, 50])
            ->emptyStateHeading('Sin usuarios')
            ->emptyStateDescription('Cada persona que entra al panel necesita su propia cuenta y un rol.');
    }
}
