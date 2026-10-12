<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Actions\EditAction;
use App\Filament\Resources\Cotizacions\Actions\ConvertirACotizacionAction;
use App\Filament\Resources\Cotizacions\Actions\DescargarCotizacionAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCotizacion extends ViewRecord
{
    protected static string $resource = CotizacionResource::class;

    /** El folio manda; debajo, de qué auto se trata. */
    public function getTitle(): string
    {
        return $this->getRecord()->folio;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->vehiculo;
    }

    protected function getHeaderActions(): array
    {
        return [
            ConvertirACotizacionAction::make(),
            DescargarCotizacionAction::make(),
            EditAction::make(),
        ];
    }
}
