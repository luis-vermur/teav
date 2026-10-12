<?php

namespace App\Filament\Resources\Contratos;

use App\Filament\Resources\Contratos\Pages\CreateContrato;
use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Filament\Resources\Contratos\Pages\ListContratos;
use App\Filament\Resources\Contratos\Pages\ViewContrato;
use App\Filament\Resources\Contratos\Schemas\ContratoForm;
use App\Filament\Resources\Contratos\Schemas\ContratoInfolist;
use App\Filament\Resources\Contratos\Tables\ContratosTable;
use App\Filament\Resources\Contratos\RelationManagers;
use App\Models\Contrato;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Illuminate\Database\Eloquent\Model;

class ContratoResource extends Resource
{
    protected static ?string $model = Contrato::class;
    protected static int $globalSearchResultsLimit = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Contratos';
    protected static ?string $modelLabel = 'contrato';
    protected static ?string $pluralModelLabel = 'contratos';
    protected static ?string $recordTitleAttribute = 'folio';

    public static function form(Schema $schema): Schema
    {
        return ContratoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContratoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContratosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PagosProgramadosRelationManager::class,
            RelationManagers\PagosRelationManager::class,
            RelationManagers\MovimientosDepositoRelationManager::class,
            RelationManagers\DocumentosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContratos::route('/'),
            'create' => CreateContrato::route('/create'),
            'view' => ViewContrato::route('/{record}'),
            'edit' => EditContrato::route('/{record}/edit'),
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
            'folio',
            // El nombre del arrendatario vive repartido en varias columnas:
            // nombre_completo es un accesor y no se puede filtrar en SQL.
            'arrendatario.nombre',
            'arrendatario.apellido_paterno',
            'arrendatario.apellido_materno',
            'arrendatario.razon_social',
            'unidad.codigo_interno',
            'unidad.vin',
        ];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Cliente'  => $record->arrendatario?->nombre_completo ?? '—',
            'Vehículo' => $record->unidad?->descripcion ?? '—',
            'Estatus'  => ucfirst(str_replace('_', ' ', $record->estatus ?? '')),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        // descripcion -> submarca.nombre_completo (usa marca) + color.nombre
        return parent::getGlobalSearchEloquentQuery()
            ->with(['arrendatario', 'unidad.submarca.marca', 'unidad.color']);
    }
}
