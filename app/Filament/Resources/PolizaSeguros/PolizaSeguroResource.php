<?php

namespace App\Filament\Resources\PolizaSeguros;

use App\Filament\Resources\PolizaSeguros\Pages\CreatePolizaSeguro;
use App\Filament\Resources\PolizaSeguros\Pages\EditPolizaSeguro;
use App\Filament\Resources\PolizaSeguros\Pages\ViewPolizaSeguro;
use App\Filament\Resources\PolizaSeguros\Pages\ListPolizaSeguros;
use App\Filament\Resources\PolizaSeguros\Schemas\PolizaSeguroForm;
use App\Filament\Resources\PolizaSeguros\Tables\PolizaSegurosTable;
use App\Filament\Resources\PolizaSeguros\Schemas\PolizaSeguroInfolist;
use App\Models\PolizaSeguro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PolizaSeguroResource extends Resource
{
    protected static ?string $model = PolizaSeguro::class;

    protected static string|UnitEnum|null $navigationGroup = 'Flota';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 22;
    protected static ?string $navigationLabel = 'Seguros';
    protected static ?string $modelLabel = 'póliza';
    protected static ?string $pluralModelLabel = 'pólizas';

    // PolizaSeguroResource

    public static function form(Schema $schema): Schema
    {
        return PolizaSeguroForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PolizaSeguroInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PolizaSegurosTable::configure($table);
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
            'index' => ListPolizaSeguros::route('/'),
            'create' => CreatePolizaSeguro::route('/create'),
            'view' => ViewPolizaSeguro::route('/{record}'),
            'edit' => EditPolizaSeguro::route('/{record}/edit'),
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
