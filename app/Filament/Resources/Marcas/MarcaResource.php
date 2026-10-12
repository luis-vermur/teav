<?php

namespace App\Filament\Resources\Marcas;

use App\Filament\Resources\Marcas\Pages\CreateMarca;
use App\Filament\Resources\Marcas\Pages\EditMarca;
use App\Filament\Resources\Marcas\Pages\ViewMarca;
use App\Filament\Resources\Marcas\Pages\ListMarcas;
use App\Filament\Resources\Marcas\Schemas\MarcaForm;
use App\Filament\Resources\Marcas\Schemas\MarcaInfolist;
use App\Filament\Resources\Marcas\Tables\MarcasTable;
use App\Models\Marca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class MarcaResource extends Resource
{
    protected static ?string $model = Marca::class;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 91;
    protected static ?string $navigationLabel = 'Marcas';
    protected static ?string $modelLabel = 'marca';
    protected static ?string $pluralModelLabel = 'marcas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return MarcaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MarcaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarcasTable::configure($table);
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
            'index' => ListMarcas::route('/'),
            'create' => CreateMarca::route('/create'),
            'view' => ViewMarca::route('/{record}'),
            'edit' => EditMarca::route('/{record}/edit'),
        ];
    }
}
