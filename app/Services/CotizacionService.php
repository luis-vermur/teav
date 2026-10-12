<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CotizacionService
{
    /**
     * Recalcula todos los indicadores de una cotización y los persiste.
     * Se llama al guardar; la cotización guarda la fotografía del cálculo.
     */
    public function calcular(Cotizacion $c): Cotizacion
    {
        $inversion = (float) $c->precio_adquisicion + (float) $c->gastos_adquisicion;
        $anios     = $c->plazo_meses / 12;

        // --- Ingresos ---
        $rentaTotal   = (float) $c->renta_con_iva * $c->plazo_meses;
        $valorVenta   = (float) $c->valor_residual_estimado;
        $ingresoBruto = $rentaTotal + $valorVenta;

        // --- Egresos estimados a lo largo del plazo ---
        $costosAnuales = (float) $c->seguro_anual_estimado
                       + (float) $c->mantenimiento_anual_estimado
                       + (float) $c->otros_costos_anuales;

        $egresos = $costosAnuales * $anios;

        // La ociosidad se cuenta como renta que no se cobrará
        $rentaDiaria      = (float) $c->renta_con_iva / 30;
        $perdidaOciosidad = $rentaDiaria * $c->dias_ociosos_estimados;
        $egresos += $perdidaOciosidad;

        $ganancia = $ingresoBruto - $inversion - $egresos;

        $div = fn (float $n) => $inversion > 0 ? round($n / $inversion, 6) : 0;

        // Meses para recuperar la inversión sólo con rentas, ya netas de costo
        $rentaNetaMensual = (float) $c->renta_con_iva - ($costosAnuales / 12);
        $equilibrio = $rentaNetaMensual > 0
            ? round($inversion / $rentaNetaMensual, 2)
            : null;

        $c->fill([
            'inversion_total'        => $inversion,
            'renta_total_contrato'   => $rentaTotal,
            'ingreso_bruto_total'    => $ingresoBruto,
            'egresos_estimados'      => $egresos,
            'ganancia_neta'          => $ganancia,
            'rendimiento_renta'      => $div($rentaTotal - $inversion),
            'rendimiento_total'      => $div($ingresoBruto - $inversion),
            'rendimiento_neto'       => $div($ganancia),
            'rendimiento_anual'      => $anios > 0 ? round($div($ganancia) / $anios, 6) : 0,
            'punto_equilibrio_meses' => $equilibrio,
        ])->save();

        return $c->fresh();
    }

    /**
     * Cálculo inverso: dado el rendimiento que se quiere, ¿cuánto hay que
     * cobrar de renta mensual?
     *
     * Es la pregunta que el Excel no podía responder sin tanteo manual.
     *
     * OJO CON EL DENOMINADOR: todos los rendimientos se miden contra
     * `precio_adquisicion` —lo que se pagó por el coche— y NO contra la
     * inversión total con gastos ni contra el precio de lista. Cambiar
     * el denominador cambia el negocio, no sólo el número.
     *
     * Fórmulas hacia adelante que aquí se despejan:
     *   renta_total       = renta_con_iva × plazo_meses
     *   rendimiento_renta = (renta_total / precio_adquisicion) − 1
     *   ingreso_bruto     = renta_total + valor_venta_unidad
     *   rendimiento_total = (ingreso_bruto / precio_adquisicion) − 1
     *   rendimiento_anual = rendimiento_total / (plazo_meses / 12)
     *
     * @param  string  $criterio  'renta' | 'total' | 'anual'
     * @param  float   $objetivo  Fracción, no porcentaje: 0.30 = 30%
     * @return array{renta_con_iva: float, renta_sin_iva: float, renta_total: float, ingreso_bruto: float, rendimiento_renta: float, rendimiento_total: float, rendimiento_anual: float, viable: bool, motivo?: string}
     */
    public function rentaParaRendimiento(
        float $precioAdquisicion,
        string $criterio,
        float $objetivo,
        int $plazoMeses,
        float $valorVentaUnidad = 0,
    ): array {
        // Sin precio o sin plazo no hay nada que despejar (y el plazo es divisor).
        if ($precioAdquisicion <= 0 || $plazoMeses <= 0) {
            return self::noViable('Captura el precio de compra y el plazo para calcular la renta.');
        }

        $anios = $plazoMeses / 12;

        // ---------- Despeje ----------
        if ($criterio === 'renta') {
            // Sólo las rentas deben alcanzar el objetivo; la venta no cuenta.
            $rentaTotal = $precioAdquisicion * (1 + $objetivo);
        } else {
            $rendTotal    = $criterio === 'anual' ? $objetivo * $anios : $objetivo;
            $ingresoBruto = $precioAdquisicion * (1 + $rendTotal);
            $rentaTotal   = $ingresoBruto - $valorVentaUnidad;
        }

        $rentaConIva = $rentaTotal / $plazoMeses;

        if ($rentaConIva <= 0) {
            return self::noViable(
                'El valor de venta estimado ya supera el rendimiento pedido. '
                . 'Sube el objetivo o baja el valor de venta.'
            );
        }

        // Se redondea hacia arriba a la centena: un cobro con centavos se ve
        // mal en un contrato, y hacia arriba nunca queda por debajo del objetivo.
        $rentaConIva = ceil($rentaConIva / 100) * 100;

        // ---------- Recálculo hacia adelante ----------
        // Con la renta ya redondeada, para que el usuario vea el rendimiento
        // real que va a obtener y no el teórico que pidió.
        $rentaTotalReal   = $rentaConIva * $plazoMeses;
        $ingresoBrutoReal = $rentaTotalReal + $valorVentaUnidad;

        $rendimientoRenta = ($rentaTotalReal / $precioAdquisicion) - 1;
        $rendimientoTotal = ($ingresoBrutoReal / $precioAdquisicion) - 1;
        $rendimientoAnual = $rendimientoTotal / $anios;

        return [
            'renta_con_iva'     => round($rentaConIva, 2),
            'renta_sin_iva'     => round($rentaConIva / 1.16, 2),
            'renta_total'       => round($rentaTotalReal, 2),
            'ingreso_bruto'     => round($ingresoBrutoReal, 2),
            'rendimiento_renta' => round($rendimientoRenta, 6),
            'rendimiento_total' => round($rendimientoTotal, 6),
            'rendimiento_anual' => round($rendimientoAnual, 6),
            'viable'            => true,
        ];
    }

    /** Respuesta en ceros cuando el despeje no da un cobro positivo. */
    private static function noViable(string $motivo): array
    {
        return [
            'renta_con_iva'     => 0.0,
            'renta_sin_iva'     => 0.0,
            'renta_total'       => 0.0,
            'ingreso_bruto'     => 0.0,
            'rendimiento_renta' => 0.0,
            'rendimiento_total' => 0.0,
            'rendimiento_anual' => 0.0,
            'viable'            => false,
            'motivo'            => $motivo,
        ];
    }

    /**
     * Contrato en memoria equivalente a la cotización.
     *
     * No se guarda: existe para poder reutilizar
     * ContratoService::previsualizarPrimerPago() sin repetir aquí la
     * aritmética del prorrateo. Los nombres de campo coinciden con los de
     * `contratos` justamente para que esta traducción sea directa.
     */
    public function contratoEquivalente(Cotizacion $c): Contrato
    {
        return new Contrato([
            'fecha_inicio'              => $c->fecha_inicio_estimada ?? now()->addDay(),
            'dia_corte'                 => $c->dia_corte ?: 1,
            'periodicidad'              => 'mensual',
            'renta_sin_iva'             => $c->renta_sin_iva,
            'tasa_iva'                  => $c->tasa_iva,
            'renta_con_iva'             => $c->renta_con_iva,
            'plazo_meses'               => $c->plazo_meses,
            'prorratear_primer_mes'     => $c->prorratear_primer_mes,
            'cobra_seguro'              => $c->cobra_seguro,
            'monto_seguro_anual'        => $c->monto_seguro_anual,
            'gastos_iniciales'          => $c->gastos_iniciales,
            'concepto_gastos_iniciales' => $c->concepto_gastos_iniciales,
            'deposito_garantia'         => $c->deposito_garantia,
            'enganche'                  => $c->enganche,
        ]);
    }

    /**
     * Todo lo que necesita el documento comercial en PDF.
     *
     * Sólo datos que el sistema realmente tiene: el primer pago desglosado,
     * la renta mensual, el seguro y los gastos de gestoría. Nada de CAT,
     * comisión por apertura ni seguros financiados: ese es otro producto.
     */
    public function datosParaDocumento(Cotizacion $c): array
    {
        $c->loadMissing(['submarca.marca', 'unidad.submarca.marca', 'unidad.proveedor', 'arrendatario']);

        $submarca = $c->submarca ?? $c->unidad?->submarca;

        return [
            'cotizacion'  => $c,
            'primer_pago' => app(ContratoService::class)->previsualizarPrimerPago($this->contratoEquivalente($c)),
            'vehiculo'    => [
                'marca'         => $submarca?->marca?->nombre,
                'submarca'      => $submarca?->nombre,
                'version'       => $c->version ?: $c->unidad?->version,
                'anio'          => $c->anio ?: $c->unidad?->anio,
                'clasificacion' => $submarca?->segmento,
                'color'         => $c->color_descripcion ?: $c->unidad?->color?->nombre,
            ],
            // La "concesionaria" del formato es el proveedor de la unidad.
            'concesionaria' => $c->unidad?->proveedor,
            'destinatario'  => $c->arrendatario?->nombre_completo ?: $c->prospecto_nombre,
            'empresa'       => $c->arrendatario?->nombre_completo ?: $c->prospecto_empresa,
        ];
    }

    /**
     * Convierte una cotización aceptada en contrato.
     * No copia ciegamente: valida que haya unidad y arrendatario reales.
     */
    public function convertirAContrato(Cotizacion $c, array $extra = []): Contrato
    {
        if (! $c->puedeConvertirse()) {
            throw new RuntimeException(
                'La cotización debe ser comercial, estar aceptada, y tener unidad y arrendatario asignados.'
            );
        }

        return DB::transaction(function () use ($c, $extra) {
            // Si el modal no trae fecha, se respeta la que se cotizó.
            $inicio = match (true) {
                isset($extra['fecha_inicio']) => \Carbon\Carbon::parse($extra['fecha_inicio']),
                (bool) $c->fecha_inicio_estimada => $c->fecha_inicio_estimada->copy()->startOfDay(),
                default => now()->addDay()->startOfDay(),
            };

            $contrato = Contrato::create([
                'unidad_id'              => $c->unidad_id,
                'arrendatario_id'        => $c->arrendatario_id,
                'fecha_firma'            => now(),
                'fecha_inicio'           => $inicio,
                'fecha_fin'              => $inicio->copy()->addMonthsNoOverflow($c->plazo_meses),
                'plazo_meses'            => $c->plazo_meses,
                'renta_sin_iva'          => $c->renta_sin_iva,
                'tasa_iva'               => $c->tasa_iva,
                'renta_con_iva'          => $c->renta_con_iva,
                'dia_corte'              => $extra['dia_corte'] ?? $c->dia_corte ?? $inicio->day,
                'periodicidad'           => 'mensual',
                'deposito_garantia'      => $c->deposito_garantia,
                'enganche'               => $c->enganche,
                'dias_gracia'            => $extra['dias_gracia'] ?? 5,
                'tipo_penalizacion'      => $extra['tipo_penalizacion'] ?? 'porcentaje_mensual',
                'valor_penalizacion'     => $extra['valor_penalizacion'] ?? 0.05,
                'limite_km_anual'        => $c->limite_km_anual,
                'valor_residual_pactado' => $c->valor_residual_estimado,
                'con_opcion_compra'      => $c->con_opcion_compra,

                /**
                 * Cargos al cliente: seguro y gastos de gestoría.
                 *
                 * Los nombres de columna son idénticos en las dos tablas
                 * justamente para que se copien sin traducción. Sin estas
                 * cinco líneas, el contrato nacía cobrando $0 de seguro
                 * aunque la cotización dijera otra cosa, y el calendario
                 * salía sin los cargos correspondientes.
                 */
                'prorratear_primer_mes'     => $c->prorratear_primer_mes,
                'cobra_seguro'              => $c->cobra_seguro,
                'monto_seguro_anual'        => $c->monto_seguro_anual,
                'gastos_iniciales'          => $c->gastos_iniciales,
                'concepto_gastos_iniciales' => $c->concepto_gastos_iniciales,
                'kilometraje_inicial'    => $c->unidad->kilometraje_actual,
                'clausulas_especiales'   => $c->condiciones,
                'notas'                  => "Generado desde cotización {$c->folio}",
                'estatus'                => 'borrador',
                'registrado_por'         => auth()->id(),
            ]);

            $c->update([
                'estatus'     => 'convertida',
                'contrato_id' => $contrato->id,
            ]);

            return $contrato;
        });
    }

    /** Marca como vencidas las cotizaciones cuya vigencia ya pasó. */
    public function expirarVencidas(): int
    {
        return Cotizacion::vencidas()->update(['estatus' => 'vencida']);
    }
}
