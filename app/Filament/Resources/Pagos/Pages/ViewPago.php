<?php

namespace App\Filament\Resources\Pagos\Pages;

use App\Filament\Resources\Pagos\PagoResource;
use App\Filament\Resources\Pagos\Actions\AplicarPagoAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPago extends ViewRecord
{
    protected static string $resource = PagoResource::class;

    /** Encabezado humano: qué es este registro, no su id. */
    public function getTitle(): string
    {
        $r = $this->getRecord();

        return $r->folio;
    }

    public function getSubheading(): ?string
    {
        $r = $this->getRecord();

        return trim(implode(' — ', array_filter([$r->arrendatario?->nombre_completo, '$' . number_format((float) $r->monto, 2)]))) ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            AplicarPagoAction::make(),
            EditAction::make(),
        ];
    }
}
