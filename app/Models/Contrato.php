<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Contrato extends Model implements AuditableContract
{
    
    use SoftDeletes, HasDocumentos, Auditable;
    use RellenaNulosConElDefault;

    protected $table = 'contratos';

    protected $auditExclude = ['updated_at', 'created_at'];

    protected $fillable = [
        'folio', 'unidad_id', 'arrendatario_id',
        'fecha_firma', 'fecha_inicio', 'fecha_fin', 'plazo_meses',
        'renta_sin_iva', 'tasa_iva', 'renta_con_iva', 'dia_corte', 'periodicidad',
        'deposito_garantia', 'enganche', 'otros_cargos_iniciales', 'concepto_otros_cargos',
        'dias_gracia', 'tipo_penalizacion', 'valor_penalizacion', 'tope_penalizacion',
        'kilometraje_inicial', 'limite_km_anual', 'costo_km_excedente',
        'valor_residual_pactado', 'con_opcion_compra',
        'estatus', 'renovacion_de', 'clausulas_especiales', 'notas', 'registrado_por',
        // Cargos al cliente además de la renta
        'prorratear_primer_mes', 'cobra_seguro', 'monto_seguro_anual',
        'gastos_iniciales', 'concepto_gastos_iniciales',
    ];

    protected function casts(): array
    {
        return [
            'fecha_firma'            => 'date',
            'fecha_inicio'           => 'date',
            'fecha_fin'              => 'date',
            'renta_sin_iva'          => 'decimal:2',
            'tasa_iva'               => 'decimal:4',
            'renta_con_iva'          => 'decimal:2',
            'deposito_garantia'      => 'decimal:2',
            'enganche'               => 'decimal:2',
            'otros_cargos_iniciales' => 'decimal:2',
            'valor_penalizacion'     => 'decimal:4',
            'tope_penalizacion'      => 'decimal:2',
            'valor_residual_pactado' => 'decimal:2',
            'costo_km_excedente'     => 'decimal:2',
            'con_opcion_compra'      => 'boolean',
            'prorratear_primer_mes'  => 'boolean',
            'cobra_seguro'           => 'boolean',
            'monto_seguro_anual'     => 'decimal:2',
            'gastos_iniciales'       => 'decimal:2',
        ];
    }

    // ---------- Relaciones ----------

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function arrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class);
    }

    public function pagosProgramados(): HasMany
    {
        /**
         * Por FECHA, no por número: desde que el calendario mezcla rentas,
         * seguros y gastos, varias filas comparten numero_exhibicion y ese
         * número dejó de marcar el orden cronológico.
         */
        return $this->hasMany(PagoProgramado::class)
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero_exhibicion');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function penalizaciones(): HasMany
    {
        return $this->hasMany(Penalizacion::class);
    }

    public function movimientosDeposito(): HasMany
    {
        return $this->hasMany(MovimientoDeposito::class);
    }

    public function cierre(): HasOne
    {
        return $this->hasOne(CierreContrato::class);
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    public function contratoOriginal(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'renovacion_de');
    }

    public function renovaciones(): HasMany
    {
        return $this->hasMany(Contrato::class, 'renovacion_de');
    }

    // ---------- Cálculos ----------

    /** El "Renta total contrato" del Excel. */
    public function getRentaTotalContratoAttribute(): float
    {
        return (float) $this->renta_con_iva * $this->plazo_meses;
    }

    public function getMontoInicialAttribute(): float
    {
        return (float) $this->deposito_garantia
             + (float) $this->enganche
             + (float) $this->otros_cargos_iniciales;
    }

    public function saldoDeposito(): float
    {
        return (float) $this->movimientosDeposito()->sum('monto');
    }

    public function saldoVencido(): float
    {
        return (float) $this->pagosProgramados()
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
            ->where('fecha_limite', '<', now())
            ->sum('saldo');
    }

    public function saldoTotalPendiente(): float
    {
        return (float) $this->pagosProgramados()
                ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
                ->sum('saldo')
            + (float) $this->penalizaciones()
                ->whereIn('estatus', ['pendiente', 'parcial'])
                ->sum('saldo');
    }

    public function exhibicionesPagadas(): int
    {
        return $this->pagosProgramados()->where('estatus', 'pagado')->count();
    }

    public function avance(): float
    {
        return $this->plazo_meses > 0
            ? round($this->exhibicionesPagadas() / $this->plazo_meses * 100, 1)
            : 0;
    }

    // ---------- Estado ----------

    public function estaVigente(): bool
    {
        return in_array($this->estatus, ['vigente', 'en_incumplimiento', 'por_vencer'], true);
    }

    /**
     * Todo lo que impide activar el contrato, no sólo lo primero.
     *
     * validarActivacion() lanza excepción en el PRIMER problema, que sirve
     * para bloquear pero no para planear: si te faltan tres cosas, quieres
     * verlas juntas y no descubrirlas de una en una.
     *
     * @return array<int, string>
     */
    public function motivosParaNoActivar(): array
    {
        if ($this->estatus === 'vigente') {
            return ['ya está vigente'];
        }

        if (! in_array($this->estatus, ['borrador', 'por_firmar'], true)) {
            return ['está en «' . ucfirst(str_replace('_', ' ', $this->estatus)) . '», ya no se puede activar'];
        }

        $motivos = [];

        if ($this->pagosProgramados()->exists()) {
            $motivos[] = 'ya tiene calendario generado';
        }

        if ($u = $this->unidad) {
            if (! in_array($u->estatus, ['disponible', 'apartada'], true)) {
                $motivos[] = "el vehículo {$u->codigo_interno} está «"
                    . ucfirst(str_replace('_', ' ', $u->estatus)) . '», debe estar disponible';
            }

            if (! $u->polizaVigente()) {
                $motivos[] = "el vehículo {$u->codigo_interno} no tiene póliza vigente";
            }
        } else {
            $motivos[] = 'no tiene vehículo asignado';
        }

        if ($a = $this->arrendatario) {
            if ($a->estatus !== 'activo') {
                $motivos[] = 'el cliente está en «' . ucfirst(str_replace('_', ' ', $a->estatus))
                    . '», debe estar en «Activo»';
            }

            if ($a->tieneAdeudoVencido()) {
                $motivos[] = 'el cliente debe $' . number_format($a->saldoVencido(), 2) . ' en otro contrato';
            }

            if ($faltantes = $a->documentosFaltantes()) {
                $motivos[] = 'al cliente le faltan documentos: ' . implode(', ', $faltantes);
            }
        } else {
            $motivos[] = 'no tiene cliente asignado';
        }

        return $motivos;
    }

    public function puedeActivarse(): bool
    {
        return empty($this->motivosParaNoActivar());
    }

    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_fin, false);
    }

    public function estaEnIncumplimiento(): bool
    {
        return $this->saldoVencido() > 0;
    }

    // ---------- Scopes ----------

    public function scopeVigentes($query)
    {
        return $query->whereIn('estatus', ['vigente', 'en_incumplimiento', 'por_vencer']);
    }

    public function scopeConAdeudo($query)
    {
        return $query->whereHas('pagosProgramados', function ($q) {
            $q->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
              ->where('fecha_limite', '<', now());
        });
    }

    public function scopePorVencer($query, int $dias = 60)
    {
        return $query->vigentes()
            ->whereBetween('fecha_fin', [now(), now()->addDays($dias)]);
    }

    // ---------- Folio automático ----------

    protected static function booted(): void
    {
        static::creating(function (Contrato $c) {
            if (! $c->folio) {
                $anio = now()->year;
                $ultimo = static::withTrashed()
                    ->where('folio', 'like', "CTO-{$anio}-%")
                    ->orderByDesc('folio')
                    ->value('folio');

                $consecutivo = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;
                $c->folio = sprintf('CTO-%d-%04d', $anio, $consecutivo);
            }
        });
    }
}