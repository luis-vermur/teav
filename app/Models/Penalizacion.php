<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;
use App\Traits\HasDocumentos;

class Penalizacion extends Model implements AuditableContract
{
    use HasDocumentos, Auditable;

    protected $table = 'penalizaciones';

    protected $auditExclude = ['updated_at', 'created_at'];

    protected $fillable = [
        'contrato_id',
        'pago_programado_id',
        'tipo',
        'fecha_generacion',
        'dias_atraso',
        'base_calculo',
        'monto',
        'monto_aplicado',
        'saldo',
        'estatus',
        'descripcion',
        'motivo_condonacion',
        'condonada_por',
        'fecha_condonacion',
        'generada_automaticamente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_generacion'         => 'date',
            'fecha_condonacion'        => 'date',
            'base_calculo'             => 'decimal:2',
            'monto'                    => 'decimal:2',
            'monto_aplicado'           => 'decimal:2',
            'saldo'                    => 'decimal:2',
            'dias_atraso'              => 'integer',
            'generada_automaticamente' => 'boolean',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function pagoProgramado(): BelongsTo
    {
        return $this->belongsTo(PagoProgramado::class);
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AplicacionPago::class);
    }

    public function condonadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'condonada_por');
    }

    public function recalcular(): void
    {
        $aplicado = (float) $this->aplicaciones()->sum('monto');
        $saldo    = round((float) $this->monto - $aplicado, 2);

        $this->update([
            'monto_aplicado' => $aplicado,
            'saldo'          => max($saldo, 0),
            'estatus'        => match (true) {
                $this->estatus === 'condonada' => 'condonada',
                $saldo <= 0                    => 'pagada',
                $aplicado > 0                  => 'parcial',
                default                        => 'pendiente',
            },
        ]);
    }

    public function condonar(string $motivo, ?int $userId = null): void
    {
        $this->update([
            'estatus'            => 'condonada',
            'saldo'              => 0,
            'motivo_condonacion' => $motivo,
            'condonada_por'      => $userId ?? auth()->id(),
            'fecha_condonacion'  => now(),
        ]);
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('estatus', ['pendiente', 'parcial']);
    }
}
