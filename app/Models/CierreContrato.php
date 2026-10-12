<?php

namespace App\Models;

use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CierreContrato extends Model
{
    use HasDocumentos;

    protected $table = 'cierres_contrato';

    protected $fillable = [
        'contrato_id', 'tipo_cierre', 'fecha_cierre',
        'kilometraje_final', 'km_excedente', 'cargo_km_excedente',
        'costo_acondicionamiento', 'cargo_daños', 'adeudo_pendiente',
        'monto_venta', 'comprador_nombre', 'comprador_arrendatario_id',
        'deposito_retenido', 'deposito_devuelto',
        'inventario_entrega', 'observaciones', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre'            => 'date',
            'cargo_km_excedente'      => 'decimal:2',
            'costo_acondicionamiento' => 'decimal:2',
            'cargo_daños'             => 'decimal:2',
            'adeudo_pendiente'        => 'decimal:2',
            'monto_venta'             => 'decimal:2',
            'deposito_retenido'       => 'decimal:2',
            'deposito_devuelto'       => 'decimal:2',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function compradorArrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class, 'comprador_arrendatario_id');
    }

    public function huboVenta(): bool
    {
        return in_array($this->tipo_cierre, ['venta_arrendatario', 'venta_tercero'], true);
    }
}