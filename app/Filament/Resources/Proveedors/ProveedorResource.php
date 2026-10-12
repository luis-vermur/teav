<?php

namespace App\Filament\Resources\Proveedors;

use App\Filament\Resources\Proveedors\Pages\CreateProveedor;
use App\Filament\Resources\Proveedors\Pages\EditProveedor;
use App\Filament\Resources\Proveedors\Pages\ViewProveedor;
use App\Filament\Resources\Proveedors\Pages\ListProveedors;
use App\Filament\Resources\Proveedors\Schemas\ProveedorForm;
use App\Filament\Resources\Proveedors\Tables\ProveedorsTable;
use App\Filament\Resources\Proveedors\Schemas\ProveedorInfolist;
use App\Models\Proveedor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?int $navigationSort = 31;
    protected static ?string $navigationLabel = 'Proveedores y talleres';
    protected static ?string $modelLabel = 'proveedor';
    protected static ?string $pluralModelLabel = 'proveedores';

    public static function form(Schema $schema): Schema
    {
        return ProveedorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProveedorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProveedorsTable::configure($table);
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
            'index' => ListProveedors::route('/'),
            'create' => CreateProveedor::route('/create'),
            'view' => ViewProveedor::route('/{record}'),
            'edit' => EditProveedor::route('/{record}/edit'),
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
