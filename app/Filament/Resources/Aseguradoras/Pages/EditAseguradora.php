<?php

namespace App\Filament\Resources\Aseguradoras\Pages;

use App\Filament\Resources\Aseguradoras\AseguradoraResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAseguradora extends EditRecord
{
    protected static string $resource = AseguradoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
