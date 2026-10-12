<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siniestro extends Model
{
    use SoftDeletes, HasDocumentos;
    use RellenaNulosConElDefault;

    protected $table = 'siniestros';

    protected $fillable = [
        'unidad_id', 'poliza_seguro_id', 'contrato_id', 'arrendatario_id',
        'numero_reporte', 'fecha_siniestro', 'tipo', 'lugar', 'descripcion',
        'monto_estimado', 'deducible_pagado', 'monto_recuperado',
        'responsable_deducible', 'estatus', 'fecha_cierre', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_siniestro'  => 'datetime',
            'fecha_cierre'     => 'date',
            'monto_estimado'   => 'decimal:2',
            'deducible_pagado' => 'decimal:2',
            'monto_recuperado' => 'decimal:2',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function poliza(): BelongsTo
    {
        return $this->belongsTo(PolizaSeguro::class, 'poliza_seguro_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function arrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class);
    }

    public function scopeAbiertos($query)
    {
        return $query->whereNotIn('estatus', ['cerrado', 'perdida_total']);
    }
}