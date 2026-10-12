<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Documento extends Model
{
    use SoftDeletes;

    protected $table = 'documentos';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'tipo_documento_id',
        'nombre_original',
        'ruta',
        'disco',
        'mime',
        'tamano',
        'hash',
        'fecha_emision',
        'fecha_vencimiento',
        'version',
        'vigente',
        'reemplaza_a',
        'notas',
        'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'     => 'date',
            'fecha_vencimiento' => 'date',
            'vigente'           => 'boolean',
            'tamano'            => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    // ---------- Estado ----------

    public function estaVencido(): bool
    {
        return $this->fecha_vencimiento?->isPast() ?? false;
    }

    public function diasParaVencer(): ?int
    {
        return $this->fecha_vencimiento
            ? now()->startOfDay()->diffInDays($this->fecha_vencimiento, false)
            : null;
    }

    public function estaPorVencer(): bool
    {
        $dias = $this->diasParaVencer();

        return $dias !== null
            && $dias >= 0
            && $dias <= ($this->tipoDocumento->dias_alerta_previa ?? 30);
    }

    // ---------- Archivo ----------

    /** URL temporal y firmada. Nunca exponer la ruta directa. */
    public function urlDescarga(int $minutos = 5): string
    {
        return \URL::temporarySignedRoute(
            'documentos.descargar',
            now()->addMinutes($minutos),
            ['documento' => $this->id]
        );
    }

    public function getTamanoLegibleAttribute(): string
    {
        $bytes = $this->tamano;

        return match (true) {
            $bytes >= 1048576 => round($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024    => round($bytes / 1024, 1) . ' KB',
            default           => $bytes . ' B',
        };
    }

    // ---------- Scopes ----------

    public function scopeVigentes($query)
    {
        return $query->where('vigente', true);
    }

    public function scopePorVencer($query, int $dias = 30)
    {
        return $query->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)])
            ->where('vigente', true);
    }

    public function scopeVencidos($query)
    {
        return $query->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now())
            ->where('vigente', true);
    }

    /** Al borrar el registro, borrar también el archivo físico. */
    protected static function booted(): void
    {
        static::forceDeleted(function (Documento $doc) {
            Storage::disk($doc->disco)->delete($doc->ruta);
        });
    }
}
