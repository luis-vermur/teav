<?php

namespace App\Filament\Resources\Unidads\Pages;

use App\Filament\Resources\Unidads\UnidadResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUnidad extends ViewRecord
{
    protected static string $resource = UnidadResource::class;

    /** El encabezado dice qué auto es, no su clave interna. */
    public function getTitle(): string
    {
        return $this->getRecord()->descripcion;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->codigo_interno;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
