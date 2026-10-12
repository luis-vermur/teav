<?php

namespace App\Filament\Resources\PolizaSeguros\Pages;

use App\Filament\Resources\PolizaSeguros\PolizaSeguroResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPolizaSeguro extends ViewRecord
{
    protected static string $resource = PolizaSeguroResource::class;

    /** Encabezado humano: qué es este registro, no su id. */
    public function getTitle(): string
    {
        $r = $this->getRecord();

        return trim(($r->aseguradora?->nombre ?? 'Póliza') . ' — ' . $r->numero_poliza);
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
