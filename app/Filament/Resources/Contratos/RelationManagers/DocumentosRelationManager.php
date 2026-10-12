<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Filament\RelationManagers\DocumentosRelationManager as Base;

/** Papeles del contrato: el firmado, el pagaré, las actas de entrega. */
class DocumentosRelationManager extends Base
{
    protected function carpeta(): string
    {
        return 'contratos';
    }

    protected function queSubir(): string
    {
        return 'Sube el contrato firmado, el pagaré y las actas de entrega y devolución.';
    }
}
