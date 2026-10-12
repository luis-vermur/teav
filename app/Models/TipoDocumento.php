<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';

    protected $fillable = [
        'nombre', 'clave', 'aplica_a', 'es_obligatorio',
        'requiere_vigencia', 'dias_alerta_previa', 'permite_multiples',
        'mimes_permitidos', 'tamano_max_mb', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_obligatorio'    => 'boolean',
            'requiere_vigencia' => 'boolean',
            'permite_multiples' => 'boolean',
            'mimes_permitidos'  => 'array',
            'activo'            => 'boolean',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function scopeParaEntidad($query, string $clase)
    {
        return $query->where('aplica_a', $clase)->where('activo', true);
    }
}