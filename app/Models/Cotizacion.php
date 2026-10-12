<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Cotizacion extends Model implements AuditableContract
{
    use SoftDeletes, HasDocumentos, Auditable;
    use RellenaNulosConElDefault;

    protected $auditExclude = ['updated_at', 'created_at'];

    protected $table = 'cotizaciones';

    protected $fillable = [
        'folio', 'proposito', 'estatus',
        'unidad_id', 'submarca_id', 'anio', 'version', 'color_descripcion',
        'precio_lista', 'precio_adquisicion', 'gastos_adquisicion', 'inversion_total',
        'arrendatario_id', 'prospecto_nombre', 'prospecto_email',
        'prospecto_telefono', 'prospecto_empresa',
        'plazo_meses', 'renta_sin_iva', 'tasa_iva', 'renta_con_iva',
        'deposito_garantia', 'enganche', 'valor_residual_estimado',
        'con_opcion_compra', 'limite_km_anual',
        'seguro_anual_estimado', 'mantenimiento_anual_estimado',
        'otros_costos_anuales', 'dias_ociosos_estimados',
        'renta_total_contrato', 'ingreso_bruto_total', 'egresos_estimados',
        'ganancia_neta', 'rendimiento_renta', 'rendimiento_total',
        'rendimiento_neto', 'rendimiento_anual', 'punto_equilibrio_meses',
        // Cargos al cliente (mismos nombres que en contratos)
        'fecha_inicio_estimada', 'dia_corte', 'prorratear_primer_mes',
        'cobra_seguro', 'monto_seguro_anual',
        'gastos_iniciales', 'concepto_gastos_iniciales',
        'fecha_cotizacion', 'vigencia_hasta', 'fecha_respuesta',
        'motivo_rechazo', 'contrato_id', 'condiciones', 'notas', 'creada_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_estimada'   => 'date',
            'prorratear_primer_mes'   => 'boolean',
            'cobra_seguro'            => 'boolean',
            'monto_seguro_anual'      => 'decimal:2',
            'gastos_iniciales'        => 'decimal:2',
            'fecha_cotizacion'        => 'date',
            'vigencia_hasta'          => 'date',
            'fecha_respuesta'         => 'date',
            'precio_lista'            => 'decimal:2',
            'precio_adquisicion'      => 'decimal:2',
            'gastos_adquisicion'      => 'decimal:2',
            'inversion_total'         => 'decimal:2',
            'renta_sin_iva'           => 'decimal:2',
            'tasa_iva'                => 'decimal:4',
            'renta_con_iva'           => 'decimal:2',
            'deposito_garantia'       => 'decimal:2',
            'enganche'                => 'decimal:2',
            'valor_residual_estimado' => 'decimal:2',
            'ganancia_neta'           => 'decimal:2',
            'rendimiento_renta'       => 'decimal:6',
            'rendimiento_total'       => 'decimal:6',
            'rendimiento_neto'        => 'decimal:6',
            'rendimiento_anual'       => 'decimal:6',
            'con_opcion_compra'       => 'boolean',
        ];
    }

    // ---------- Relaciones ----------

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function submarca(): BelongsTo
    {
        return $this->belongsTo(Submarca::class);
    }

    public function arrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    // ---------- Presentación ----------

    /** El vehículo puede ser real o hipotético; esto unifica ambos. */
    public function getVehiculoAttribute(): string
    {
        if ($this->unidad) {
            return $this->unidad->descripcion;
        }

        return trim(implode(' ', array_filter([
            $this->submarca?->nombre_completo,
            $this->anio,
            $this->version,
        ]))) ?: 'Vehículo sin especificar';
    }

    public function getDestinatarioAttribute(): ?string
    {
        return $this->arrendatario?->nombre_completo
            ?? $this->prospecto_nombre
            ?? $this->prospecto_empresa;
    }

    public function esComercial(): bool
    {
        return $this->proposito === 'comercial';
    }

    public function estaVigente(): bool
    {
        return $this->vigencia_hasta === null
            || $this->vigencia_hasta->isFuture();
    }

    /**
     * Qué le falta a la cotización para poder convertirse en contrato.
     *
     * Existe para que el botón pueda EXPLICAR por qué no se puede, en vez
     * de simplemente no aparecer y dejar al usuario sin saber que la
     * función existe.
     *
     * @return array<int, string>
     */
    public function motivosParaNoConvertirse(): array
    {
        $motivos = [];

        if (! $this->esComercial()) {
            $motivos[] = 'el propósito debe ser «Propuesta a cliente», no análisis interno';
        }

        if ($this->estatus !== 'aceptada') {
            $motivos[] = 'la cotización debe estar en estatus «Aceptada»';
        }

        if ($this->contrato_id !== null) {
            $motivos[] = 'ya se convirtió en el contrato ' . ($this->contrato?->folio ?? '');
        }

        if ($this->unidad_id === null) {
            $motivos[] = 'falta asignarle una unidad de la flota';
        }

        if ($this->arrendatario_id === null) {
            $motivos[] = 'falta asignarle un cliente registrado';
        }

        return $motivos;
    }

    public function puedeConvertirse(): bool
    {
        return $this->esComercial()
            && $this->estatus === 'aceptada'
            && $this->contrato_id === null
            && $this->unidad_id !== null
            && $this->arrendatario_id !== null;
    }

    // ---------- Scopes ----------

    public function scopeAnalisis($query)
    {
        return $query->where('proposito', 'analisis_compra');
    }

    public function scopeComerciales($query)
    {
        return $query->where('proposito', 'comercial');
    }

    public function scopeAbiertas($query)
    {
        return $query->whereIn('estatus', ['borrador', 'enviada']);
    }

    public function scopeVencidas($query)
    {
        return $query->whereIn('estatus', ['borrador', 'enviada'])
            ->whereNotNull('vigencia_hasta')
            ->where('vigencia_hasta', '<', now());
    }

    // ---------- Folio ----------

    protected static function booted(): void
    {
        static::creating(function (Cotizacion $c) {
            if (! $c->folio) {
                $anio   = now()->year;
                $ultimo = static::withTrashed()
                    ->where('folio', 'like', "COT-{$anio}-%")
                    ->orderByDesc('folio')
                    ->value('folio');

                $consecutivo = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;
                $c->folio = sprintf('COT-%d-%04d', $anio, $consecutivo);
            }
        });

        /**
         * `inversion_total` es NOT NULL y el formulario no lo captura: es un
         * valor derivado. Se calcula aquí —y no sólo en CotizacionService—
         * porque el servicio corre DESPUÉS de que la fila existe, así que
         * crear desde el panel reventaba con una violación de not-null.
         *
         * Al hacerlo en `saving` queda cubierta cualquier vía de entrada:
         * panel, tinker, importación o seeder.
         */
        static::saving(function (Cotizacion $c) {
            $c->inversion_total = (float) $c->precio_adquisicion
                                + (float) $c->gastos_adquisicion;
        });
    }
}
