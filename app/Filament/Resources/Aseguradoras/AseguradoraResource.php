<?php

namespace App\Filament\Resources\Aseguradoras;

use App\Filament\Resources\Aseguradoras\Pages\CreateAseguradora;
use App\Filament\Resources\Aseguradoras\Pages\EditAseguradora;
use App\Filament\Resources\Aseguradoras\Pages\ViewAseguradora;
use App\Filament\Resources\Aseguradoras\Pages\ListAseguradoras;
use App\Filament\Resources\Aseguradoras\Schemas\AseguradoraForm;
use App\Filament\Resources\Aseguradoras\Schemas\AseguradoraInfolist;
use App\Filament\Resources\Aseguradoras\Tables\AseguradorasTable;
use App\Models\Aseguradora;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AseguradoraResource extends Resource
{
    protected static ?string $model = Aseguradora::class;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 94;
    protected static ?string $navigationLabel = 'Aseguradoras';
    protected static ?string $modelLabel = 'aseguradora';
    protected static ?string $pluralModelLabel = 'aseguradoras';


    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return AseguradoraForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AseguradoraInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AseguradorasTable::configure($table);
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
            'index' => ListAseguradoras::route('/'),
            'create' => CreateAseguradora::route('/create'),
            'view' => ViewAseguradora::route('/{record}'),
            'edit' => EditAseguradora::route('/{record}/edit'),
        ];
    }
}
