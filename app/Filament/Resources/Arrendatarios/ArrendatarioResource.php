<?php

namespace App\Filament\Resources\Arrendatarios;

use UnitEnum;

use App\Filament\Resources\Arrendatarios\Pages\CreateArrendatario;
use App\Filament\Resources\Arrendatarios\Pages\EditArrendatario;
use App\Filament\Resources\Arrendatarios\Pages\ListArrendatarios;
use App\Filament\Resources\Arrendatarios\Pages\ViewArrendatario;
use App\Filament\Resources\Arrendatarios\Schemas\ArrendatarioForm;
use App\Filament\Resources\Arrendatarios\Schemas\ArrendatarioInfolist;
use App\Filament\Resources\Arrendatarios\Tables\ArrendatariosTable;
use App\Filament\Resources\Arrendatarios\RelationManagers;
use App\Models\Arrendatario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Htmlable;

class ArrendatarioResource extends Resource
{
    protected static ?string $model = Arrendatario::class;
    protected static int $globalSearchResultsLimit = 5;
    protected static ?string $recordTitleAttribute = 'codigo';

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 30;
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $modelLabel = 'cliente';
    protected static ?string $pluralModelLabel = 'clientes';

    public static function form(Schema $schema): Schema
    {
        return ArrendatarioForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ArrendatarioInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArrendatariosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // El expediente del cliente: sin él no se puede activar contrato.
            RelationManagers\DocumentosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArrendatarios::route('/'),
            'create' => CreateArrendatario::route('/create'),
            'view' => ViewArrendatario::route('/{record}'),
            'edit' => EditArrendatario::route('/{record}/edit'),
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
            'codigo',
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'razon_social',
            'rfc',
            'email',
            'telefono',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->nombre_completo;
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'RFC'      => $record->rfc ?? '—',
            'Teléfono' => $record->telefono ?? '—',
            'Estatus'  => ucfirst(str_replace('_', ' ', $record->estatus ?? '')),
        ];
    }
}
