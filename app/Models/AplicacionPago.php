<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AplicacionPago extends Model
{
    protected $table = 'aplicaciones_pago';

    protected $fillable = [
        'pago_id', 'pago_programado_id', 'penalizacion_id', 'concepto', 'monto',
    ];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function pagoProgramado(): BelongsTo
    {
        return $this->belongsTo(PagoProgramado::class);
    }

    public function penalizacion(): BelongsTo
    {
        return $this->belongsTo(Penalizacion::class);
    }
}