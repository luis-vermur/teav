<?php

namespace App\Models;

use App\Models\Concerns\RellenaNulosConElDefault;
use App\Traits\HasDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Arrendatario extends Model implements AuditableContract
{
    use SoftDeletes, HasDocumentos, Auditable;
    use RellenaNulosConElDefault;

    protected $table = 'arrendatarios';

    protected $auditExclude = ['updated_at', 'created_at'];

    protected $fillable = [
        'codigo',
        'tipo_persona',
        'rfc',
        'regimen_fiscal',
        'email',
        'telefono',
        'telefono_alterno',
        // Física
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'curp',
        'fecha_nacimiento',
        'nacionalidad',
        'ocupacion',
        'licencia_numero',
        'licencia_vigencia',
        // Moral
        'razon_social',
        'nombre_comercial',
        'fecha_constitucion',
        'acta_constitutiva_numero',
        'representante_legal_nombre',
        'representante_legal_rfc',
        'representante_legal_curp',
        'representante_legal_telefono',
        'representante_legal_email',
        // Control
        'estatus',
        'calificacion_interna',
        'ingreso_mensual_declarado',
        'limite_credito',
        'motivo_estatus',
        'notas',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento'          => 'date',
            'licencia_vigencia'         => 'date',
            'fecha_constitucion'        => 'date',
            'ingreso_mensual_declarado' => 'decimal:2',
            'limite_credito'            => 'decimal:2',
            'calificacion_interna'      => 'integer',
        ];
    }

    // ---------- Relaciones ----------

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function referencias(): HasMany
    {
        return $this->hasMany(Referencia::class);
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    public function siniestros(): HasMany
    {
        return $this->hasMany(Siniestro::class);
    }

    public function direcciones(): MorphMany
    {
        return $this->morphMany(Direccion::class, 'direccionable');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    // ---------- Identidad ----------

    public function esFisica(): bool
    {
        return $this->tipo_persona === 'fisica';
    }

    public function esMoral(): bool
    {
        return $this->tipo_persona === 'moral';
    }

    /**
     * Un solo nombre para mostrar, sin importar el tipo de persona.
     * Úsalo en tablas, selects y encabezados.
     */
    public function getNombreCompletoAttribute(): string
    {
        if ($this->esMoral()) {
            return $this->razon_social ?? $this->nombre_comercial ?? 'Sin nombre';
        }

        return trim(implode(' ', array_filter([
            $this->nombre,
            $this->apellido_paterno,
            $this->apellido_materno,
        ]))) ?: 'Sin nombre';
    }

    public function getDireccionFiscalAttribute(): ?Direccion
    {
        return $this->direcciones()->where('tipo', 'fiscal')->first()
            ?? $this->direcciones()->where('es_principal', true)->first();
    }

    // ---------- Estado de cuenta ----------

    public function contratosVigentes(): HasMany
    {
        return $this->contratos()
            ->whereIn('estatus', ['vigente', 'en_incumplimiento', 'por_vencer']);
    }

    /** Total que debe hoy, sumando exhibiciones vencidas de todos sus contratos. */
    public function saldoVencido(): float
    {
        return (float) PagoProgramado::query()
            ->whereIn('contrato_id', $this->contratos()->select('id'))
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
            ->where('fecha_limite', '<', now())
            ->sum('saldo');
    }

    public function tieneAdeudoVencido(): bool
    {
        return $this->saldoVencido() > 0;
    }

    /**
     * Puede firmar un contrato nuevo sólo si está activo,
     * sin adeudos y con su documentación obligatoria completa.
     */
    /**
     * Qué le impide firmar un contrato hoy.
     *
     * Existe para poder DECIRLO en pantalla. Antes el cliente simplemente
     * no aparecía en el selector del asistente y no había forma de saber
     * por qué.
     *
     * @return array<int, string>
     */
    public function motivosParaNoContratar(): array
    {
        $motivos = [];

        if ($this->estatus !== 'activo') {
            $motivos[] = 'su situación es «' . ucfirst(str_replace('_', ' ', $this->estatus))
                . '», debe estar en «Activo»';
        }

        if ($this->tieneAdeudoVencido()) {
            $motivos[] = 'tiene $' . number_format($this->saldoVencido(), 2) . ' vencidos en otro contrato';
        }

        if ($faltantes = $this->documentosFaltantes()) {
            $motivos[] = 'faltan documentos: ' . implode(', ', $faltantes);
        }

        return $motivos;
    }

    public function puedeContratar(): bool
    {
        return $this->estatus === 'activo'
            && ! $this->tieneAdeudoVencido()
            && $this->tieneDocumentacionCompleta();
    }

    // ---------- Scopes ----------

    public function scopeActivos($query)
    {
        return $query->where('estatus', 'activo');
    }

    public function scopeFisicas($query)
    {
        return $query->where('tipo_persona', 'fisica');
    }

    public function scopeMorales($query)
    {
        return $query->where('tipo_persona', 'moral');
    }

    // ---------- Folio automático ----------

    protected static function booted(): void
    {
        static::creating(function (Arrendatario $a) {
            if (! $a->codigo) {
                $anio = now()->year;
                $ultimo = static::withTrashed()
                    ->where('codigo', 'like', "ARR-{$anio}-%")
                    ->orderByDesc('codigo')
                    ->value('codigo');

                $consecutivo = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;
                $a->codigo = sprintf('ARR-%d-%04d', $anio, $consecutivo);
            }
        });
    }
}
