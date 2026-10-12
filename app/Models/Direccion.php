<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Direccion extends Model
{
    protected $table = 'direcciones';

    protected $fillable = [
        'direccionable_type', 'direccionable_id', 'tipo',
        'calle', 'numero_exterior', 'numero_interior', 'colonia',
        'municipio', 'estado', 'codigo_postal', 'pais',
        'latitud', 'longitud', 'referencias', 'es_principal',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'latitud'      => 'decimal:7',
            'longitud'     => 'decimal:7',
        ];
    }

    public function direccionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getCompletaAttribute(): string
    {
        $partes = array_filter([
            $this->calle,
            $this->numero_exterior,
            $this->numero_interior ? "Int. {$this->numero_interior}" : null,
            $this->colonia,
            $this->municipio,
            $this->estado,
            "C.P. {$this->codigo_postal}",
        ]);

        return implode(', ', $partes);
    }
}