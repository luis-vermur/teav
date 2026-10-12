<?php

namespace App\Filament\Resources\Unidads;

use App\Filament\Resources\Unidads\Pages\CreateUnidad;
use App\Filament\Resources\Unidads\Pages\EditUnidad;
use App\Filament\Resources\Unidads\Pages\ListUnidads;
use App\Filament\Resources\Unidads\Pages\ViewUnidad;
use App\Filament\Resources\Unidads\Schemas\UnidadForm;
use App\Filament\Resources\Unidads\Schemas\UnidadInfolist;
use App\Filament\Resources\Unidads\Tables\UnidadsTable;
use App\Models\Unidad;
use App\Filament\Resources\Unidads\RelationManagers;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Illuminate\Database\Eloquent\Model;

class UnidadResource extends Resource
{
    protected static ?string $model = Unidad::class;
    protected static int $globalSearchResultsLimit = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Flota';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Vehículos';
    protected static ?string $modelLabel = 'vehículo';
    protected static ?string $pluralModelLabel = 'vehículos';

    // dentro de la clase
    protected static ?string $recordTitleAttribute = 'codigo_interno';

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentosRelationManager::class,
            RelationManagers\AnalisisRentabilidadRelationManager::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return UnidadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UnidadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnidadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnidads::route('/'),
            'create' => CreateUnidad::route('/create'),
            'view' => ViewUnidad::route('/{record}'),
            'edit' => EditUnidad::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'codigo_interno',
            'vin',
            'placas',
            'submarca.nombre',
            // marca no es relación en Unidad (es accesor), la ruta real es submarca.marca
            'submarca.marca.nombre',
        ];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Vehículo' => $record->descripcion,
            'Placas'   => $record->placas ?? '—',
            'Estatus'  => ucfirst(str_replace('_', ' ', $record->estatus ?? '')),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['submarca.marca', 'color']);
    }
}
