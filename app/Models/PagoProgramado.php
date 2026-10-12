<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PagoProgramado extends Model
{
    protected $table = 'pagos_programados';

    protected $fillable = [
        'contrato_id', 'numero_exhibicion', 'concepto', 'descripcion',
        'fecha_vencimiento', 'fecha_limite',
        'monto_sin_iva', 'tasa_iva', 'monto_total', 'monto_aplicado', 'saldo',
        'estatus', 'fecha_liquidacion', 'dias_atraso', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'fecha_limite'      => 'date',
            'fecha_liquidacion' => 'date',
            'monto_sin_iva'     => 'decimal:2',
            'tasa_iva'          => 'decimal:4',
            'monto_total'       => 'decimal:2',
            'monto_aplicado'    => 'decimal:2',
            'saldo'             => 'decimal:2',
            'dias_atraso'       => 'integer',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AplicacionPago::class);
    }

    public function penalizaciones(): HasMany
    {
        return $this->hasMany(Penalizacion::class);
    }

    // ---------- Estado ----------

    public function estaVencido(): bool
    {
        return $this->saldo > 0 && $this->fecha_limite->isPast();
    }

    public function diasDeAtraso(): int
    {
        if (! $this->estaVencido()) {
            return 0;
        }

        return (int) $this->fecha_limite->diffInDays(now());
    }

    /**
     * Recalcula saldo y estatus a partir de lo aplicado.
     * Llamar después de aplicar o reversar un pago.
     */
    public function recalcular(): void
    {
        $aplicado = (float) $this->aplicaciones()->sum('monto');
        $saldo    = round((float) $this->monto_total - $aplicado, 2);

        $estatus = match (true) {
            $this->estatus === 'condonado' => 'condonado',
            $saldo <= 0                    => 'pagado',
            $this->fecha_limite->isPast()  => 'vencido',
            $aplicado > 0                  => 'parcial',
            default                        => 'pendiente',
        };

        $this->update([
            'monto_aplicado'    => $aplicado,
            'saldo'             => max($saldo, 0),
            'estatus'           => $estatus,
            'dias_atraso'       => $saldo > 0 ? $this->diasDeAtraso() : 0,
            'fecha_liquidacion' => $saldo <= 0 ? ($this->fecha_liquidacion ?? now()) : null,
        ]);
    }

    // ---------- Scopes ----------

    public function scopePendientes($query)
    {
        return $query->whereIn('estatus', ['pendiente', 'parcial', 'vencido']);
    }

    public function scopeVencidos($query)
    {
        return $query->pendientes()->where('fecha_limite', '<', now());
    }

    public function scopePorVencer($query, int $dias = 7)
    {
        return $query->pendientes()
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)]);
    }
}