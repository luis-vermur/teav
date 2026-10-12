<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Contratos\Actions\ActivarContratoAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContrato extends ViewRecord
{
    protected static string $resource = ContratoResource::class;

    /** El folio manda en el encabezado; debajo, de quién y qué auto. */
    public function getTitle(): string
    {
        return $this->getRecord()->folio;
    }

    public function getSubheading(): ?string
    {
        $c = $this->getRecord();

        return trim(implode(' — ', array_filter([
            $c->arrendatario?->nombre_completo,
            $c->unidad?->descripcion,
        ]))) ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActivarContratoAction::make(),
            EditAction::make(),
        ];
    }
}
