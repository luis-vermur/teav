<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PolizaSeguro extends Model
{
    use SoftDeletes, HasDocumentos;
    use RellenaNulosConElDefault;

    protected $table = 'polizas_seguro';

    protected $fillable = [
        'unidad_id', 'aseguradora_id', 'numero_poliza', 'tipo_cobertura',
        'vigencia_inicio', 'vigencia_fin', 'prima_total', 'suma_asegurada',
        'deducible_danos', 'deducible_robo', 'forma_pago', 'estatus',
        'conductor_designado', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fin'    => 'date',
            'prima_total'     => 'decimal:2',
            'suma_asegurada'  => 'decimal:2',
            'deducible_danos' => 'decimal:4',
            'deducible_robo'  => 'decimal:4',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function aseguradora(): BelongsTo
    {
        return $this->belongsTo(Aseguradora::class);
    }

    public function siniestros(): HasMany
    {
        return $this->hasMany(Siniestro::class, 'poliza_seguro_id');
    }

    public function estaVigente(): bool
    {
        return $this->estatus === 'vigente'
            && $this->vigencia_fin->isFuture();
    }

    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->vigencia_fin, false);
    }

    public function scopePorVencer($query, int $dias = 30)
    {
        return $query->where('estatus', 'vigente')
            ->whereBetween('vigencia_fin', [now(), now()->addDays($dias)]);
    }
}