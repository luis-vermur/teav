<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Marca extends Model
{
    protected $table = 'marcas';

    protected $fillable = ['nombre', 'pais_origen', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function submarcas(): HasMany
    {
        return $this->hasMany(Submarca::class);
    }

    /** Las unidades de la flota que son de esta marca, saltando submarcas. */
    public function unidades(): HasManyThrough
    {
        return $this->hasManyThrough(Unidad::class, Submarca::class);
    }
}