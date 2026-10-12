<?php

namespace App\Filament\Resources\Mantenimientos;

use App\Filament\Resources\Mantenimientos\Pages\CreateMantenimiento;
use App\Filament\Resources\Mantenimientos\Pages\EditMantenimiento;
use App\Filament\Resources\Mantenimientos\Pages\ViewMantenimiento;
use App\Filament\Resources\Mantenimientos\Pages\ListMantenimientos;
use App\Filament\Resources\Mantenimientos\Schemas\MantenimientoForm;
use App\Filament\Resources\Mantenimientos\Tables\MantenimientosTable;
use App\Filament\Resources\Mantenimientos\Schemas\MantenimientoInfolist;
use App\Models\Mantenimiento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class MantenimientoResource extends Resource
{
    protected static ?string $model = Mantenimiento::class;

    protected static string|UnitEnum|null $navigationGroup = 'Flota';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?int $navigationSort = 21;
    protected static ?string $navigationLabel = 'Servicios';
    protected static ?string $modelLabel = 'servicio';
    protected static ?string $pluralModelLabel = 'servicios';

    public static function form(Schema $schema): Schema
    {
        return MantenimientoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MantenimientoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MantenimientosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMantenimientos::route('/'),
            'create' => CreateMantenimiento::route('/create'),
            'view' => ViewMantenimiento::route('/{record}'),
            'edit' => EditMantenimiento::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
