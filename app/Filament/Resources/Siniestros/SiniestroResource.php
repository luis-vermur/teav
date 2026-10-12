<?php

namespace App\Filament\Resources\Siniestros;

use App\Filament\Resources\Siniestros\Pages\CreateSiniestro;
use App\Filament\Resources\Siniestros\Pages\EditSiniestro;
use App\Filament\Resources\Siniestros\Pages\ViewSiniestro;
use App\Filament\Resources\Siniestros\Pages\ListSiniestros;
use App\Filament\Resources\Siniestros\Schemas\SiniestroForm;
use App\Filament\Resources\Siniestros\Tables\SiniestrosTable;
use App\Filament\Resources\Siniestros\Schemas\SiniestroInfolist;
use App\Models\Siniestro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SiniestroResource extends Resource
{
    protected static ?string $model = Siniestro::class;

    protected static string|UnitEnum|null $navigationGroup = 'Flota';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 23;
    protected static ?string $navigationLabel = 'Siniestros';
    protected static ?string $modelLabel = 'siniestro';
    protected static ?string $pluralModelLabel = 'siniestros';

    // SiniestroResource

    public static function form(Schema $schema): Schema
    {
        return SiniestroForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SiniestroInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiniestrosTable::configure($table);
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
            'index' => ListSiniestros::route('/'),
            'create' => CreateSiniestro::route('/create'),
            'view' => ViewSiniestro::route('/{record}'),
            'edit' => EditSiniestro::route('/{record}/edit'),
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
