<?php

namespace App\Filament\Resources\Submarcas;

use App\Filament\Resources\Submarcas\Pages\CreateSubmarca;
use App\Filament\Resources\Submarcas\Pages\EditSubmarca;
use App\Filament\Resources\Submarcas\Pages\ViewSubmarca;
use App\Filament\Resources\Submarcas\Pages\ListSubmarcas;
use App\Filament\Resources\Submarcas\Schemas\SubmarcaForm;
use App\Filament\Resources\Submarcas\Schemas\SubmarcaInfolist;
use App\Filament\Resources\Submarcas\Tables\SubmarcasTable;
use App\Models\Submarca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SubmarcaResource extends Resource
{
    protected static ?string $model = Submarca::class;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 92;
    protected static ?string $navigationLabel = 'Modelos';
    protected static ?string $modelLabel = 'modelo';
    protected static ?string $pluralModelLabel = 'modelos';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return SubmarcaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SubmarcaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubmarcasTable::configure($table);
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
            'index' => ListSubmarcas::route('/'),
            'create' => CreateSubmarca::route('/create'),
            'view' => ViewSubmarca::route('/{record}'),
            'edit' => EditSubmarca::route('/{record}/edit'),
        ];
    }
}
