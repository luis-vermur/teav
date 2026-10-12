<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisRentabilidad extends Model
{
    protected $table = 'analisis_rentabilidad';

    protected $fillable = [
        'unidad_id', 'contrato_id', 'tipo', 'nombre_escenario', 'fecha_calculo',
        'precio_lista', 'precio_adquisicion', 'gastos_adquisicion', 'inversion_total',
        'renta_con_iva', 'plazo_meses', 'renta_total_contrato', 'valor_venta',
        'ingresos_otros', 'ingreso_bruto_total',
        'costo_seguros', 'costo_mantenimientos', 'costo_tenencias',
        'costo_deducibles', 'costo_otros', 'egresos_totales',
        'ganancia_neta', 'rendimiento_renta', 'rendimiento_total',
        'rendimiento_neto', 'rendimiento_anual',
        'dias_ociosos', 'ocupacion', 'supuestos', 'calculado_por',
        // Riesgo: qué separa la realidad de la proyección
        'perdida_ociosidad', 'perdida_cartera', 'diferencia_residual',
        'rendimiento_proyectado', 'brecha_rendimiento',
    ];

    protected function casts(): array
    {
        return [
            'fecha_calculo'        => 'date',
            'inversion_total'      => 'decimal:2',
            'ingreso_bruto_total'  => 'decimal:2',
            'egresos_totales'      => 'decimal:2',
            'ganancia_neta'        => 'decimal:2',
            'rendimiento_renta'    => 'decimal:6',
            'rendimiento_total'    => 'decimal:6',
            'rendimiento_neto'     => 'decimal:6',
            'rendimiento_anual'    => 'decimal:6',
            'ocupacion'            => 'decimal:4',
            'perdida_ociosidad'      => 'decimal:2',
            'perdida_cartera'        => 'decimal:2',
            'diferencia_residual'    => 'decimal:2',
            'rendimiento_proyectado' => 'decimal:6',
            'brecha_rendimiento'     => 'decimal:6',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function esProyeccion(): bool
    {
        return $this->tipo === 'proyeccion';
    }
}