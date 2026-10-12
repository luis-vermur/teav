<?php

namespace App\Filament\Resources\PolizaSeguros\Pages;

use App\Filament\Resources\PolizaSeguros\PolizaSeguroResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPolizaSeguro extends EditRecord
{
    protected static string $resource = PolizaSeguroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
