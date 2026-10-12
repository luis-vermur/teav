<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoDeposito extends Model
{
    protected $table = 'movimientos_deposito';

    protected $fillable = [
        'contrato_id', 'tipo', 'fecha', 'monto',
        'saldo_resultante', 'motivo', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha'            => 'date',
            'monto'            => 'decimal:2',
            'saldo_resultante' => 'decimal:2',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}