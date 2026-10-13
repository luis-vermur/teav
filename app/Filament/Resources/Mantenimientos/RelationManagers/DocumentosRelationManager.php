<?php

namespace App\Filament\Resources\Mantenimientos\RelationManagers;

use App\Filament\RelationManagers\DocumentosRelationManager as Base;

/**
 * Papeles del servicio: la factura del taller y la orden de trabajo.
 *
 * La factura importa más de lo que parece. Cuando el servicio se marca como
 * recobrable, su costo se le carga al cliente, y es el documento que respalda
 * ese cargo si alguien lo reclama meses después. Sin ella, el recobro es la
 * palabra de la arrendadora contra la del arrendatario.
 */
class DocumentosRelationManager extends Base
{
    protected function carpeta(): string
    {
        return 'mantenimientos';
    }

    protected function queSubir(): string
    {
        return 'Sube la factura del taller y la orden de trabajo. '
            . 'La factura es el respaldo del cargo si este servicio se le recobra al cliente.';
    }
}
