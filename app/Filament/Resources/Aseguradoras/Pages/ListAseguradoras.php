<?php

namespace App\Filament\Resources\Aseguradoras\Pages;

use App\Filament\Resources\Aseguradoras\AseguradoraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAseguradoras extends ListRecords
{
    protected static string $resource = AseguradoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
