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

class Pago extends Model implements AuditableContract
{
    use SoftDeletes, HasDocumentos, Auditable;
    use RellenaNulosConElDefault;

    protected $table = 'pagos';

    protected $auditExclude = ['updated_at', 'created_at'];

    protected $fillable = [
        'folio', 'contrato_id', 'arrendatario_id', 'fecha_pago',
        'monto', 'monto_aplicado', 'saldo_a_favor',
        'metodo_pago', 'referencia', 'cuenta_destino', 'banco_origen',
        'estatus', 'motivo_reverso', 'notas',
        'registrado_por', 'confirmado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago'     => 'date',
            'monto'          => 'decimal:2',
            'monto_aplicado' => 'decimal:2',
            'saldo_a_favor'  => 'decimal:2',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function arrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class);
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AplicacionPago::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    /** Dinero de este pago que aún no se aplica a nada. */
    public function montoDisponible(): float
    {
        return round((float) $this->monto - (float) $this->aplicaciones()->sum('monto'), 2);
    }

    public function scopeAplicados($query)
    {
        return $query->where('estatus', 'aplicado');
    }

    public function scopeVigentes($query)
    {
        return $query->whereNotIn('estatus', ['reversado', 'rechazado']);
    }

    protected static function booted(): void
    {
        static::creating(function (Pago $p) {
            if (! $p->folio) {
                $anio = now()->year;
                $ultimo = static::withTrashed()
                    ->where('folio', 'like', "PAG-{$anio}-%")
                    ->orderByDesc('folio')
                    ->value('folio');

                $consecutivo = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;
                $p->folio = sprintf('PAG-%d-%06d', $anio, $consecutivo);
            }

            // Quién capturó el pago se toma de la sesión: es un dato de
            // trazabilidad, no algo que el usuario deba teclear como número.
            if (! $p->registrado_por) {
                $p->registrado_por = auth()->id();
            }
        });
    }
}
