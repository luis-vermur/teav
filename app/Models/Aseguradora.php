<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Aseguradora extends Model
{
    protected $table = 'aseguradoras';

    protected $fillable = [
        'nombre', 'rfc', 'telefono_siniestros',
        'contacto_nombre', 'contacto_email', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function polizas(): HasMany
    {
        return $this->hasMany(PolizaSeguro::class);
    }

    public function direcciones(): MorphMany
    {
        return $this->morphMany(Direccion::class, 'direccionable');
    }
}