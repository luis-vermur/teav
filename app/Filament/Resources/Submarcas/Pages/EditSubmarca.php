<?php

namespace App\Filament\Resources\Submarcas\Pages;

use App\Filament\Resources\Submarcas\SubmarcaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubmarca extends EditRecord
{
    protected static string $resource = SubmarcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
