<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Unidad extends Model implements AuditableContract
{
    use SoftDeletes, HasDocumentos, Auditable;
    use RellenaNulosConElDefault;

    protected $table = 'unidades';

    protected $auditExclude = ['updated_at', 'created_at'];

    protected $fillable = [
        'codigo_interno', 'vin', 'placas', 'numero_motor',
        'submarca_id', 'color_id', 'anio', 'version',
        'transmision', 'combustible', 'numero_pasajeros',
        'fecha_adquisicion', 'proveedor_id', 'factura_folio',
        'precio_lista', 'precio_adquisicion', 'gastos_adquisicion',
        'forma_adquisicion', 'valor_residual_estimado', 'valor_venta_real',
        'kilometraje_inicial', 'kilometraje_actual', 'fecha_ultimo_kilometraje',
        'estatus', 'ubicacion', 'tiene_gps', 'gps_identificador',
        'notas', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_adquisicion'        => 'date',
            'fecha_ultimo_kilometraje' => 'date',
            'precio_lista'             => 'decimal:2',
            'precio_adquisicion'       => 'decimal:2',
            'gastos_adquisicion'       => 'decimal:2',
            'valor_residual_estimado'  => 'decimal:2',
            'valor_venta_real'         => 'decimal:2',
            'tiene_gps'                => 'boolean',
        ];
    }

    // ---------- Relaciones ----------

    public function submarca(): BelongsTo
    {
        return $this->belongsTo(Submarca::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    public function polizas(): HasMany
    {
        return $this->hasMany(PolizaSeguro::class);
    }

    public function siniestros(): HasMany
    {
        return $this->hasMany(Siniestro::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function historialEstatus(): HasMany
    {
        return $this->hasMany(UnidadHistorialEstatus::class);
    }

    /**
     * Historial de cálculos de rentabilidad de esta unidad.
     * Los genera el comando diario `teav:rentabilidad`.
     */
    public function analisisRentabilidad(): HasMany
    {
        return $this->hasMany(AnalisisRentabilidad::class)->latest('fecha_calculo');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    // ---------- Accesores ----------

    /** "Audi Q3 2025 Gris" */
    public function getDescripcionAttribute(): string
    {
        return trim(sprintf(
            '%s %s %s',
            $this->submarca?->nombre_completo,
            $this->anio,
            $this->color?->nombre ?? ''
        ));
    }

    public function getMarcaAttribute(): ?Marca
    {
        return $this->submarca?->marca;
    }

    /** Lo que realmente costó poner la unidad a rentar. */
    public function getInversionTotalAttribute(): float
    {
        return (float) $this->precio_adquisicion + (float) $this->gastos_adquisicion;
    }

    /** Descuento logrado contra precio de lista. */
    public function getDescuentoObtenidoAttribute(): ?float
    {
        if (! $this->precio_lista) {
            return null;
        }

        return (float) $this->precio_lista - (float) $this->precio_adquisicion;
    }

    /** Póliza vigente hoy, si la hay. */
    public function polizaVigente(): ?PolizaSeguro
    {
        return $this->polizas()
            ->where('estatus', 'vigente')
            ->where('vigencia_fin', '>=', now())
            ->latest('vigencia_fin')
            ->first();
    }

    public function contratoVigente(): ?Contrato
    {
        return $this->contratos()
            ->whereIn('estatus', ['vigente', 'en_incumplimiento', 'por_vencer'])
            ->latest('fecha_inicio')
            ->first();
    }

    // ---------- Lógica ----------

    public function estaDisponible(): bool
    {
        return $this->estatus === 'disponible';
    }

    /**
     * Cambia el estatus y deja rastro en la bitácora.
     * Cierra el periodo anterior y abre uno nuevo.
     */
    public function cambiarEstatus(string $nuevo, ?string $motivo = null): void
    {
        if ($this->estatus === $nuevo) {
            return;
        }

        $anterior = $this->estatus;

        $abierto = $this->historialEstatus()->whereNull('fin')->latest('inicio')->first();

        if ($abierto) {
            $abierto->update([
                'fin'  => now(),
                'dias' => (int) $abierto->inicio->diffInDays(now()),
            ]);
        }

        $this->historialEstatus()->create([
            'estatus_anterior' => $anterior,
            'estatus_nuevo'    => $nuevo,
            'inicio'           => now(),
            'motivo'           => $motivo,
            'registrado_por'   => auth()->id(),
        ]);

        $this->update(['estatus' => $nuevo]);
    }

    // ---------- Scopes ----------

    public function scopeDisponibles($query)
    {
        return $query->where('estatus', 'disponible');
    }

    public function scopeArrendadas($query)
    {
        return $query->where('estatus', 'arrendada');
    }

    public function scopeActivas($query)
    {
        return $query->whereNotIn('estatus', ['vendida', 'baja']);
    }
}