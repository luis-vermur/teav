<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contacto extends Model
{
    protected $table = 'contactos';

    protected $fillable = [
        'arrendatario_id', 'nombre', 'puesto', 'rol',
        'telefono', 'email', 'es_principal',
    ];

    protected function casts(): array
    {
        return ['es_principal' => 'boolean'];
    }

    public function arrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class);
    }
}