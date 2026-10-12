<?php

namespace App\Filament\Resources\Submarcas\Pages;

use App\Filament\Resources\Submarcas\SubmarcaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubmarcas extends ListRecords
{
    protected static string $resource = SubmarcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
