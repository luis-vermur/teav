<?php

namespace App\Filament\Resources\Unidads\RelationManagers;

use App\Filament\RelationManagers\DocumentosRelationManager as Base;

/** Papeles del vehículo: factura, tarjeta de circulación, verificación. */
class DocumentosRelationManager extends Base
{
    protected function carpeta(): string
    {
        return 'unidades';
    }

    protected function queSubir(): string
    {
        return 'Sube la factura de origen, la tarjeta de circulación y demás papeles de esta unidad.';
    }
}
