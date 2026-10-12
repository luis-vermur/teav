<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadHistorialEstatus extends Model
{
    protected $table = 'unidad_historial_estatus';

    protected $fillable = [
        'unidad_id', 'estatus_anterior', 'estatus_nuevo',
        'inicio', 'fin', 'dias', 'motivo', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin'    => 'datetime',
            'dias'   => 'integer',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Periodos improductivos: disponible sin rentar. */
    public function scopeOciosos($query)
    {
        return $query->where('estatus_nuevo', 'disponible');
    }
}