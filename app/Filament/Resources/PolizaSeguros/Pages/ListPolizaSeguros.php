<?php

namespace App\Filament\Resources\PolizaSeguros\Pages;

use App\Filament\Resources\PolizaSeguros\PolizaSeguroResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPolizaSeguros extends ListRecords
{
    protected static string $resource = PolizaSeguroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
