<?php

namespace App\Filament\Resources\Cotizacions;

use App\Filament\Resources\Cotizacions\Pages\CreateCotizacion;
use App\Filament\Resources\Cotizacions\Pages\EditCotizacion;
use App\Filament\Resources\Cotizacions\Pages\ListCotizacions;
use App\Filament\Resources\Cotizacions\Pages\ViewCotizacion;
use App\Filament\Resources\Cotizacions\Schemas\CotizacionForm;
use App\Filament\Resources\Cotizacions\Schemas\CotizacionInfolist;
use App\Filament\Resources\Cotizacions\Tables\CotizacionsTable;
use App\Models\Cotizacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CotizacionResource extends Resource
{
    protected static ?string $model = Cotizacion::class;
    protected static int $globalSearchResultsLimit = 5;
    protected static ?string $recordTitleAttribute = 'folio';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationLabel = 'Cotizaciones';
    protected static ?string $modelLabel = 'cotización';
    protected static ?string $pluralModelLabel = 'cotizaciones';

    public static function form(Schema $schema): Schema
    {
        return CotizacionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CotizacionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CotizacionsTable::configure($table);
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
            'index' => ListCotizacions::route('/'),
            'create' => CreateCotizacion::route('/create'),
            'view' => ViewCotizacion::route('/{record}'),
            'edit' => EditCotizacion::route('/{record}/edit'),
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
        return ['folio'];
    }
}
