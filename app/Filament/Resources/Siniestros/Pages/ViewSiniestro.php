<?php

namespace App\Filament\Resources\Siniestros\Pages;

use App\Filament\Resources\Siniestros\SiniestroResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSiniestro extends ViewRecord
{
    protected static string $resource = SiniestroResource::class;

    /** Encabezado humano: qué es este registro, no su id. */
    public function getTitle(): string
    {
        $r = $this->getRecord();

        return trim(match ($r->tipo) { 'colision' => 'Colisión', 'robo_total' => 'Robo total', 'robo_parcial' => 'Robo parcial', 'cristales' => 'Cristales', 'daños_terceros' => 'Daños a terceros', default => 'Siniestro' } . ' — ' . $r->fecha_siniestro?->format('d/M/Y'));
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
