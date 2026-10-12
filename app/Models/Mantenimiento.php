<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mantenimiento extends Model
{
    use SoftDeletes, HasDocumentos;
    use RellenaNulosConElDefault;

    protected $table = 'mantenimientos';

    protected $fillable = [
        'unidad_id', 'tipo_mantenimiento_id', 'proveedor_id', 'contrato_id',
        'fecha', 'kilometraje', 'costo', 'costo_iva', 'responsable_costo',
        'proximo_fecha', 'proximo_kilometraje', 'folio_taller',
        'descripcion', 'refacciones', 'estatus', 'registrado_por',
        'se_recobra',
    ];

    protected function casts(): array
    {
        return [
            'fecha'         => 'date',
            'proximo_fecha' => 'date',
            'costo'         => 'decimal:2',
            'costo_iva'     => 'decimal:2',
            'kilometraje'   => 'integer',
            'se_recobra'    => 'boolean',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoMantenimiento::class, 'tipo_mantenimiento_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function getCostoTotalAttribute(): float
    {
        return (float) $this->costo + (float) $this->costo_iva;
    }

    /** Sólo cuenta contra el rendimiento si lo pagó la arrendadora. */
    public function afectaRentabilidad(): bool
    {
        return $this->responsable_costo === 'arrendadora';
    }

    /**
     * La arrendadora lo adelantó y aún no se lo cobra al cliente.
     * Es dinero suyo en la calle, aunque no sea un gasto propio.
     */
    public function pendienteDeRecobro(): bool
    {
        if (! $this->se_recobra) {
            return false;
        }

        $cargo = PagoProgramado::where('concepto', 'recobro')
            ->where('descripcion', 'like', "%[MTO-{$this->id}]%")
            ->first();

        return $cargo === null || (float) $cargo->saldo > 0;
    }

    /**
     * Al guardar, si la arrendadora adelantó el pago se genera el cargo
     * al cliente. Va como evento del modelo y no en el formulario para que
     * también aplique desde importaciones, seeders o tinker.
     */
    protected static function booted(): void
    {
        static::saved(function (Mantenimiento $m) {
            app(\App\Services\MantenimientoService::class)->generarRecobro($m);
        });
    }

    public function scopeVencidos($query)
    {
        return $query->whereNotNull('proximo_fecha')
            ->where('proximo_fecha', '<', now())
            ->where('estatus', 'completado');
    }

    public function scopeProximos($query, int $dias = 30)
    {
        return $query->whereNotNull('proximo_fecha')
            ->whereBetween('proximo_fecha', [now(), now()->addDays($dias)]);
    }
}