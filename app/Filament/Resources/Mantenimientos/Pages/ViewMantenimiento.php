<?php

namespace App\Filament\Resources\Mantenimientos\Pages;

use App\Filament\Resources\Mantenimientos\MantenimientoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMantenimiento extends ViewRecord
{
    protected static string $resource = MantenimientoResource::class;

    /** Encabezado humano: qué es este registro, no su id. */
    public function getTitle(): string
    {
        $r = $this->getRecord();

        return trim(($r->tipo?->nombre ?? 'Servicio') . ' — ' . $r->fecha?->format('d/M/Y'));
    }

    public function getSubheading(): ?string
    {
        $r = $this->getRecord();

        return $r->unidad?->descripcion;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
