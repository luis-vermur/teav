<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submarca extends Model
{
    protected $table = 'submarcas';

    protected $fillable = ['marca_id', 'nombre', 'segmento', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    /** "Audi Q3" en un solo string, para selects y tablas. */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->marca->nombre} {$this->nombre}";
    }
}