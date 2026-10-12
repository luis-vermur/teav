<?php

namespace App\Filament\Resources\Arrendatarios\Pages;

use App\Filament\Resources\Arrendatarios\ArrendatarioResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewArrendatario extends ViewRecord
{
    protected static string $resource = ArrendatarioResource::class;

    /** El encabezado dice quién es, no su clave interna. */
    public function getTitle(): string
    {
        return $this->getRecord()->nombre_completo;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->codigo;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
