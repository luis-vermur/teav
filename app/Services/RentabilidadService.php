<?php

namespace App\Services;

use App\Models\AnalisisRentabilidad;
use App\Models\Contrato;
use App\Models\Unidad;

class RentabilidadService
{
    /**
     * Proyección: lo que se espera ganar según lo pactado.
     * Equivale a lo que calculaba el Excel, pero sumando gastos de
     * adquisición a la inversión (el Excel sólo usaba el precio con descuento).
     */
    public function proyectar(Contrato $contrato): AnalisisRentabilidad
    {
        $unidad    = $contrato->unidad;
        $inversion = $unidad->inversion_total;

        $rentaTotal  = $contrato->renta_total_contrato;
        $valorVenta  = (float) ($contrato->valor_residual_pactado
                        ?? $unidad->valor_residual_estimado
                        ?? 0);

        $ingresoBruto = $rentaTotal + $valorVenta;

        /**
         * En este modelo de negocio la arrendadora sólo pone el capital: el
         * cliente absorbe seguro, tenencia, mantenimientos, llantas y todo
         * gasto derivado del uso. La proyección de egresos es, por diseño,
         * prácticamente cero.
         *
         * La única excepción es que el contrato diga expresamente que la
         * arrendadora absorbe la póliza (`cobra_seguro` apagado). Dejar ese
         * caso en cero escondería un costo que sí existe.
         */
        $anios = $contrato->plazo_meses / 12;

        $costoSeguros = $contrato->cobra_seguro
            ? 0.0
            : (float) ($unidad->polizaVigente()?->prima_total ?? 0) * $anios;

        return $this->guardar($unidad, $contrato, 'proyeccion', [
            'renta_con_iva'        => $contrato->renta_con_iva,
            'plazo_meses'          => $contrato->plazo_meses,
            'renta_total_contrato' => $rentaTotal,
            'valor_venta'          => $valorVenta,
            'ingresos_otros'       => 0,
            'ingreso_bruto_total'  => $ingresoBruto,
            'costo_seguros'        => $costoSeguros,
            'costo_mantenimientos' => 0,
            'costo_tenencias'      => 0,
            'costo_deducibles'     => 0,
            'costo_otros'          => 0,
            'dias_ociosos'         => 0,
            'inversion'            => $inversion,
        ]);
    }

    /**
     * Real: lo que efectivamente entró y salió.
     * Aquí es donde el sistema dice cosas que el Excel no podía.
     */
    public function calcularReal(Unidad $unidad): AnalisisRentabilidad
    {
        $inversion = $unidad->inversion_total;
        $contrato  = $unidad->contratoVigente() ?? $unidad->contratos()->latest('fecha_inicio')->first();

        /**
         * --- Ingresos realmente cobrados ---
         *
         * Lo cobrado por seguro y por gastos de gestoría/placas NO es
         * ingreso: es el reembolso de un dinero que la arrendadora ya había
         * desembolsado. Contarlo inflaría el rendimiento con dinero que
         * nunca fue ganancia. El ingreso relevante son sólo las rentas.
         */
        $rentaCobrada = (float) \App\Models\AplicacionPago::query()
            ->where('concepto', 'renta')
            ->whereHas('pago', fn ($q) => $q
                ->whereIn('contrato_id', $unidad->contratos()->select('id'))
                ->where('estatus', 'aplicado'))
            // Seguro, gastos de gestoría y recobros de mantenimiento son
            // REEMBOLSOS: ese dinero entró porque antes había salido. No es
            // ganancia y contarlo inflaría el rendimiento.
            ->whereDoesntHave('pagoProgramado', fn ($q) => $q
                ->whereIn('concepto', ['seguro', 'gastos_iniciales', 'recobro']))
            ->sum('monto');

        $penalizacionesCobradas = (float) \App\Models\AplicacionPago::query()
            ->where('concepto', 'penalizacion')
            ->whereHas('pago', fn ($q) => $q
                ->whereIn('contrato_id', $unidad->contratos()->select('id'))
                ->where('estatus', 'aplicado'))
            ->sum('monto');

        $valorVenta = (float) ($unidad->valor_venta_real ?? 0);

        $ingresoBruto = $rentaCobrada + $penalizacionesCobradas + $valorVenta;

        /**
         * --- Egresos reales de la arrendadora ---
         *
         * Con este modelo quedan MUY pocos, y ésa es justamente la idea:
         *   - Seguro y tenencia: los paga el cliente. Cero.
         *   - Mantenimientos: sólo los que quedaron a nombre de la
         *     arrendadora; con el default en `arrendatario` deberían ser
         *     casi inexistentes.
         *   - Deducibles de siniestro a cargo de la arrendadora: sí cuentan.
         *   - Acondicionamiento al cierre del contrato: sí cuenta, es el
         *     costo de dejar la unidad presentable para volver a rentarla.
         */
        $costoSeguros = ($contrato && $contrato->cobra_seguro)
            ? 0.0
            : (float) $unidad->polizas()->sum('prima_total');

        $costoMantenimientos = (float) $unidad->mantenimientos()
            ->where('responsable_costo', 'arrendadora')
            ->get()
            ->sum(fn ($m) => $m->costo_total);

        $costoDeducibles = (float) $unidad->siniestros()
            ->where('responsable_deducible', 'arrendadora')
            ->sum('deducible_pagado');

        $costoAcondicionamiento = (float) \App\Models\CierreContrato::query()
            ->whereIn('contrato_id', $unidad->contratos()->select('id'))
            ->sum('costo_acondicionamiento');

        // --- Ociosidad ---
        $diasOciosos = (int) $unidad->historialEstatus()
            ->where('estatus_nuevo', 'disponible')
            ->whereNotNull('fin')
            ->sum('dias');

        $diasTotales = $unidad->fecha_adquisicion
            ? (int) $unidad->fecha_adquisicion->diffInDays(now())
            : 0;

        $ocupacion = $diasTotales > 0
            ? round(1 - ($diasOciosos / $diasTotales), 4)
            : null;

        /**
         * --- Métricas de riesgo ---
         *
         * Como los costos ya no explican nada, lo que separa al rendimiento
         * real del proyectado es otra cosa: el auto parado, la cartera que
         * nunca se cobró y haberlo vendido por debajo de lo estimado.
         */

        // Lo que se dejó de ganar por tener la unidad sin rentar.
        $rentaDiaria = $contrato && $contrato->renta_con_iva > 0
            ? (float) $contrato->renta_con_iva / 30
            : 0.0;

        $perdidaOciosidad = round($diasOciosos * $rentaDiaria, 2);

        // Saldos que quedaron colgados en contratos que ya terminaron:
        // si el contrato se cerró debiendo, ese dinero ya no llega.
        $perdidaCartera = (float) \App\Models\PagoProgramado::query()
            ->whereIn('contrato_id', $unidad->contratos()
                ->whereIn('estatus', ['finalizado', 'rescindido'])
                ->select('id'))
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
            ->sum('saldo');

        // Negativo si se vendió por debajo de lo que se esperaba.
        $diferenciaResidual = $unidad->valor_venta_real !== null
            ? round((float) $unidad->valor_venta_real - (float) $unidad->valor_residual_estimado, 2)
            : 0.0;

        // Contra qué se compara: la última proyección de esta unidad.
        $proyeccion = AnalisisRentabilidad::query()
            ->where('unidad_id', $unidad->id)
            ->where('tipo', 'proyeccion')
            ->latest('fecha_calculo')
            ->first();

        return $this->guardar($unidad, $contrato, 'real', [
            'renta_con_iva'          => $contrato?->renta_con_iva ?? 0,
            'plazo_meses'            => $contrato?->plazo_meses ?? 0,
            'renta_total_contrato'   => $rentaCobrada,
            'valor_venta'            => $valorVenta,
            'ingresos_otros'         => $penalizacionesCobradas,
            'ingreso_bruto_total'    => $ingresoBruto,
            'costo_seguros'          => $costoSeguros,
            'costo_mantenimientos'   => $costoMantenimientos,
            // El cliente paga la tenencia: nunca es egreso de la arrendadora.
            'costo_tenencias'        => 0,
            'costo_deducibles'       => $costoDeducibles,
            'costo_otros'            => $costoAcondicionamiento,
            'dias_ociosos'           => $diasOciosos,
            'ocupacion'              => $ocupacion,
            'inversion'              => $inversion,

            'perdida_ociosidad'      => $perdidaOciosidad,
            'perdida_cartera'        => $perdidaCartera,
            'diferencia_residual'    => $diferenciaResidual,
            'rendimiento_proyectado' => $proyeccion?->rendimiento_anual,
        ]);
    }

    /**
     * Aplica las fórmulas y persiste. Centralizado para que
     * proyección y real usen exactamente el mismo cálculo.
     */
    protected function guardar(Unidad $unidad, ?Contrato $contrato, string $tipo, array $d): AnalisisRentabilidad
    {
        $inversion = (float) $d['inversion'];

        $egresos = (float) $d['costo_seguros']
                 + (float) $d['costo_mantenimientos']
                 + (float) $d['costo_tenencias']
                 + (float) $d['costo_deducibles']
                 + (float) $d['costo_otros'];

        $ingresoBruto = (float) $d['ingreso_bruto_total'];
        $ganancia     = $ingresoBruto - $inversion - $egresos;

        // Guarda contra división entre cero
        $div = fn (float $n) => $inversion > 0 ? round($n / $inversion, 6) : 0;

        $anios = ($d['plazo_meses'] ?: 12) / 12;

        $rendimientoAnual = $anios > 0 ? round($div($ganancia) / $anios, 6) : 0;

        $rendimientoProyectado = isset($d['rendimiento_proyectado'])
            ? (float) $d['rendimiento_proyectado']
            : null;

        return AnalisisRentabilidad::create([
            'unidad_id'            => $unidad->id,
            'contrato_id'          => $contrato?->id,
            'tipo'                 => $tipo,
            'fecha_calculo'        => now(),

            'precio_lista'         => $unidad->precio_lista,
            'precio_adquisicion'   => $unidad->precio_adquisicion,
            'gastos_adquisicion'   => $unidad->gastos_adquisicion,
            'inversion_total'      => $inversion,

            'renta_con_iva'        => $d['renta_con_iva'],
            'plazo_meses'          => $d['plazo_meses'],
            'renta_total_contrato' => $d['renta_total_contrato'],
            'valor_venta'          => $d['valor_venta'],
            'ingresos_otros'       => $d['ingresos_otros'],
            'ingreso_bruto_total'  => $ingresoBruto,

            'costo_seguros'        => $d['costo_seguros'],
            'costo_mantenimientos' => $d['costo_mantenimientos'],
            'costo_tenencias'      => $d['costo_tenencias'],
            'costo_deducibles'     => $d['costo_deducibles'],
            'costo_otros'          => $d['costo_otros'],
            'egresos_totales'      => $egresos,

            'ganancia_neta'        => $ganancia,

            // Mismas fórmulas del Excel
            'rendimiento_renta'    => $div((float) $d['renta_total_contrato'] - $inversion),
            'rendimiento_total'    => $div($ingresoBruto - $inversion),
            // Ésta es nueva: ya descuenta costos operativos
            'rendimiento_neto'     => $div($ganancia),
            'rendimiento_anual'    => $rendimientoAnual,

            'dias_ociosos'         => $d['dias_ociosos'],
            'ocupacion'            => $d['ocupacion'] ?? null,

            // --- Riesgo: por qué la realidad se separa de la proyección ---
            'perdida_ociosidad'      => $d['perdida_ociosidad'] ?? 0,
            'perdida_cartera'        => $d['perdida_cartera'] ?? 0,
            'diferencia_residual'    => $d['diferencia_residual'] ?? 0,
            'rendimiento_proyectado' => $rendimientoProyectado,
            'brecha_rendimiento'     => $rendimientoProyectado !== null
                ? round($rendimientoAnual - $rendimientoProyectado, 6)
                : null,

            'calculado_por'        => auth()->id(),
        ]);
    }
}