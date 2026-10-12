<?php

namespace App\Filament\Resources\Siniestros\Pages;

use App\Filament\Resources\Siniestros\SiniestroResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiniestros extends ListRecords
{
    protected static string $resource = SiniestroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
