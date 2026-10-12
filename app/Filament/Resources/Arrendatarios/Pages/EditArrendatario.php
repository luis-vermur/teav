<?php

namespace App\Filament\Resources\Arrendatarios\Pages;

use App\Filament\Resources\Arrendatarios\ArrendatarioResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditArrendatario extends EditRecord
{
    protected static string $resource = ArrendatarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
