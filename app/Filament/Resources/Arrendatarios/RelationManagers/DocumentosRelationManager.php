<?php

namespace App\Filament\Resources\Arrendatarios\RelationManagers;

use App\Filament\RelationManagers\DocumentosRelationManager as Base;

/**
 * Expediente del cliente.
 *
 * Sin los tres obligatorios (identificación, comprobante de domicilio y
 * constancia de situación fiscal) el contrato no se puede activar: lo
 * valida ContratoService::validarActivacion().
 */
class DocumentosRelationManager extends Base
{
    protected static ?string $title = 'Expediente';

    protected function carpeta(): string
    {
        return 'clientes';
    }

    protected function queSubir(): string
    {
        return 'Sube su identificación, comprobante de domicilio y constancia de situación fiscal. Sin el expediente completo no podrás activarle un contrato.';
    }
}
