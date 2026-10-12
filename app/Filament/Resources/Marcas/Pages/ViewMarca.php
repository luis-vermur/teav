<?php

namespace App\Filament\Resources\Marcas\Pages;

use App\Filament\Resources\Marcas\MarcaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMarca extends ViewRecord
{
    protected static string $resource = MarcaResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->nombre;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->pais_origen;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
