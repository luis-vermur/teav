<?php

namespace App\Filament\Resources\Proveedors\Pages;

use App\Filament\Resources\Proveedors\ProveedorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProveedor extends ViewRecord
{
    protected static string $resource = ProveedorResource::class;

    /** Encabezado humano: qué es este registro, no su id. */
    public function getTitle(): string
    {
        $r = $this->getRecord();

        return $r->nombre;
    }

    public function getSubheading(): ?string
    {
        $r = $this->getRecord();

        return collect($r->tipos ?? [])->map(fn ($t) => ucfirst($t))->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
