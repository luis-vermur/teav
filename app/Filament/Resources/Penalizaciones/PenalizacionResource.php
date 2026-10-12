<?php

namespace App\Filament\Resources\Penalizaciones;

use App\Filament\Resources\Penalizaciones\Pages\ListPenalizaciones;
use App\Filament\Resources\Penalizaciones\Pages\ViewPenalizacion;
use App\Filament\Resources\Penalizaciones\Schemas\PenalizacionInfolist;
use App\Filament\Resources\Penalizaciones\Tables\PenalizacionesTable;
use App\Models\Penalizacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Recargos por mora y otros cargos al cliente.
 *
 * No tiene alta manual a propósito: las penalizaciones las genera el
 * comando de cobranza a partir de las exhibiciones vencidas. Aquí se
 * consultan y, con el permiso adecuado, se condonan.
 */
class PenalizacionResource extends Resource
{
    protected static ?string $model = Penalizacion::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationLabel = 'Recargos';
    protected static ?string $modelLabel = 'recargo';
    protected static ?string $pluralModelLabel = 'recargos';
    protected static ?string $recordTitleAttribute = 'descripcion';

    /** Se generan solas: no hay formulario de alta. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PenalizacionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenalizacionesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenalizaciones::route('/'),
            'view'  => ViewPenalizacion::route('/{record}'),
        ];
    }

    /** Un recargo pendiente es dinero por cobrar: se avisa en el menú. */
    public static function getNavigationBadge(): ?string
    {
        $n = Penalizacion::whereIn('estatus', ['pendiente', 'parcial'])->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
