<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referencia extends Model
{
    protected $table = 'referencias';

    protected $fillable = [
        'arrendatario_id', 'tipo', 'nombre', 'relacion', 'telefono',
        'email', 'empresa', 'verificada', 'fecha_verificacion',
        'verificada_por', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'verificada'         => 'boolean',
            'fecha_verificacion' => 'date',
        ];
    }

    public function arrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class);
    }

    public function verificadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificada_por');
    }
}