<?php

namespace App\Filament\Resources\Unidads\Pages;

use App\Filament\Resources\Unidads\UnidadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnidads extends ListRecords
{
    protected static string $resource = UnidadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
