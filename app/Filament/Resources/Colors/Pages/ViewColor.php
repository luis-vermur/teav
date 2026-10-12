<?php

namespace App\Filament\Resources\Colors\Pages;

use App\Filament\Resources\Colors\ColorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewColor extends ViewRecord
{
    protected static string $resource = ColorResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->nombre;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
