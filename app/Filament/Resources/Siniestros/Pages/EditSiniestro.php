<?php

namespace App\Filament\Resources\Siniestros\Pages;

use App\Filament\Resources\Siniestros\SiniestroResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSiniestro extends EditRecord
{
    protected static string $resource = SiniestroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
