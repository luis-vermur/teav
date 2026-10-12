<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use SoftDeletes;
    use RellenaNulosConElDefault;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre', 'rfc', 'tipos', 'telefono', 'email',
        'contacto_nombre', 'notas', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipos'  => 'array',      // json <-> array de PHP
            'activo' => 'boolean',
        ];
    }

    /**
     * Red de seguridad sobre el cast.
     *
     * El formulario guardaba `tipos` como cadena suelta ("taller") en vez de
     * arreglo. Ya se corrigió en el formulario, pero un registro antiguo, un
     * seeder o una importación pueden volver a meter una cadena: sin esto,
     * cualquier foreach o in_array revienta. Aquí siempre sale un arreglo.
     */
    protected function tipos(): Attribute
    {
        return Attribute::get(fn ($value, array $attributes) => match (true) {
            blank($attributes['tipos'] ?? null) => [],
            is_array($decodificado = json_decode($attributes['tipos'], true)) => $decodificado,
            default => [$attributes['tipos']],   // era una cadena suelta
        });
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    public function unidadesVendidas(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    public function direcciones(): MorphMany
    {
        return $this->morphMany(Direccion::class, 'direccionable');
    }

    /** Filtra por tipo: Proveedor::deTipo('taller')->get() */
    public function scopeDeTipo($query, string $tipo)
    {
        return $query->whereJsonContains('tipos', $tipo);
    }
}