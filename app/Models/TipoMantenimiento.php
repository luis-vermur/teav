<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoMantenimiento extends Model
{
    protected $table = 'tipos_mantenimiento';

    protected $fillable = [
        'nombre', 'clave', 'categoria',
        'periodicidad_meses', 'periodicidad_km',
        'genera_alerta', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'genera_alerta' => 'boolean',
            'activo'        => 'boolean',
        ];
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }
}