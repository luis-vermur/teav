<?php

namespace App\Services;

use App\Models\Contrato;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContratoService
{
    /**
     * Activa un contrato: valida, genera el calendario de pagos,
     * registra el depósito y marca la unidad como arrendada.
     * Todo o nada — si algo falla, no queda nada a medias.
     */
    public function activar(Contrato $contrato): Contrato
    {
        $this->validarActivacion($contrato);

        return DB::transaction(function () use ($contrato) {
            $this->generarCalendario($contrato);

            if ($contrato->deposito_garantia > 0) {
                $contrato->movimientosDeposito()->create([
                    'tipo'             => 'recepcion',
                    'fecha'            => $contrato->fecha_inicio,
                    'monto'            => $contrato->deposito_garantia,
                    'saldo_resultante' => $contrato->deposito_garantia,
                    'motivo'           => 'Depósito en garantía al inicio del contrato',
                    'registrado_por'   => auth()->id(),
                ]);
            }

            $contrato->unidad->cambiarEstatus(
                'arrendada',
                "Contrato {$contrato->folio}"
            );

            $contrato->update([
                'estatus'             => 'vigente',
                'kilometraje_inicial' => $contrato->kilometraje_inicial
                    ?? $contrato->unidad->kilometraje_actual,
            ]);

            return $contrato->fresh();
        });
    }

    /**
     * Reglas que deben cumplirse antes de entregar una unidad.
     * Fallar aquí es barato; fallar después de entregar el auto, no.
     */
    protected function validarActivacion(Contrato $contrato): void
    {
        if ($contrato->estatus === 'vigente') {
            throw new RuntimeException('El contrato ya está vigente.');
        }

        if (! in_array($contrato->estatus, ['borrador', 'por_firmar'], true)) {
            throw new RuntimeException("No se puede activar un contrato en estatus «{$contrato->estatus}».");
        }

        if ($contrato->pagosProgramados()->exists()) {
            throw new RuntimeException('Este contrato ya tiene calendario generado.');
        }

        $unidad = $contrato->unidad;

        if (! in_array($unidad->estatus, ['disponible', 'apartada'], true)) {
            throw new RuntimeException("La unidad {$unidad->codigo_interno} no está disponible (estatus: {$unidad->estatus}).");
        }

        if (! $unidad->polizaVigente()) {
            throw new RuntimeException("La unidad {$unidad->codigo_interno} no tiene póliza de seguro vigente.");
        }

        $arrendatario = $contrato->arrendatario;

        if ($arrendatario->estatus !== 'activo') {
            throw new RuntimeException("El arrendatario no está activo (estatus: {$arrendatario->estatus}).");
        }

        if ($arrendatario->tieneAdeudoVencido()) {
            throw new RuntimeException('El arrendatario tiene adeudos vencidos en otro contrato.');
        }

        if ($faltantes = $arrendatario->documentosFaltantes()) {
            throw new RuntimeException(
                'Al arrendatario le faltan documentos: ' . implode(', ', $faltantes)
            );
        }
    }

    /**
     * Genera el calendario completo de cargos al cliente.
     *
     * Ya no son sólo rentas: en este arrendamiento el cliente también paga
     * el seguro y los gastos de gestoría/placas. La arrendadora los
     * desembolsa y los recobra, así que van como cargos programados con su
     * propio concepto, no como egresos del negocio.
     *
     * Tres bloques:
     *   A) Rentas — la primera puede ser proporcional si el contrato no
     *      arranca en el día de corte.
     *   B) Seguro — un cargo cada 12 meses mientras dure el contrato.
     *   C) Gastos iniciales — uno solo, al firmar.
     */
    public function generarCalendario(Contrato $contrato): void
    {
        $filas = array_merge(
            $this->cargosDeRenta($contrato),
            $this->cargosDeSeguro($contrato),
            $this->cargosIniciales($contrato),
        );

        // Un solo insert en lugar de uno por fila
        $contrato->pagosProgramados()->insert($filas);
    }

    /**
     * A) Rentas.
     *
     * Con prorrateo, la exhibición 1 cubre sólo los días entre el inicio y
     * el día anterior al siguiente corte, y vence al firmar. Las demás son
     * completas, en el día de corte de cada mes. El total de exhibiciones
     * de renta sigue siendo `plazo_meses`: la proporcional cuenta como la
     * primera, no se suma aparte.
     */
    protected function cargosDeRenta(Contrato $contrato): array
    {
        $periodos = $this->numeroDePeriodos($contrato);
        $filas    = [];

        $prorratea = $this->prorratea($contrato);

        if (! $prorratea) {
            // Comportamiento de siempre: todas completas.
            for ($i = 1; $i <= $periodos; $i++) {
                $filas[] = $this->filaDeCargo(
                    contrato: $contrato,
                    numero: $i,
                    concepto: 'renta',
                    descripcion: null,
                    vencimiento: $this->fechaDeVencimiento($contrato, $i),
                    montoTotal: (float) $contrato->renta_con_iva,
                    tasaIva: (float) $contrato->tasa_iva,
                );
            }

            return $filas;
        }

        // ---- Exhibición 1: proporcional ----
        $inicio        = $contrato->fecha_inicio->copy();
        $primerCorte   = $this->primerCorteDespuesDe($contrato, $inicio);
        $ultimoDia     = $primerCorte->copy()->subDay();               // día anterior al corte
        $diasCubiertos = (int) $inicio->diffInDays($ultimoDia) + 1;    // ambos inclusive
        $diasDelMes    = (int) $inicio->daysInMonth;

        $filas[] = $this->filaDeCargo(
            contrato: $contrato,
            numero: 1,
            concepto: 'renta_proporcional',
            descripcion: $this->rangoEnPalabras($inicio, $ultimoDia, $diasCubiertos),
            vencimiento: $inicio,           // se paga al firmar
            montoTotal: round((float) $contrato->renta_con_iva * $diasCubiertos / $diasDelMes, 2),
            tasaIva: (float) $contrato->tasa_iva,
        );

        // ---- Exhibiciones 2..n: completas, en el día de corte ----
        for ($i = 2; $i <= $periodos; $i++) {
            $vencimiento = $primerCorte->copy()->addMonthsNoOverflow($i - 2);
            $vencimiento->day(min((int) $contrato->dia_corte, $vencimiento->daysInMonth));

            $filas[] = $this->filaDeCargo(
                contrato: $contrato,
                numero: $i,
                concepto: 'renta',
                descripcion: null,
                vencimiento: $vencimiento,
                montoTotal: (float) $contrato->renta_con_iva,
                tasaIva: (float) $contrato->tasa_iva,
            );
        }

        return $filas;
    }

    /**
     * B) Seguro anual.
     *
     * Un cargo cada 12 meses: mes 0, 12, 24, 36... hasta cubrir el plazo.
     * Un contrato de 48 meses genera 4. El primero vence al firmar; los
     * demás en el aniversario.
     *
     * Va sin IVA desglosado a propósito: es el reembolso exacto de lo que
     * la arrendadora desembolsó por la póliza, no un servicio que preste.
     */
    protected function cargosDeSeguro(Contrato $contrato): array
    {
        if (! $contrato->cobra_seguro || (float) $contrato->monto_seguro_anual <= 0) {
            return [];
        }

        $filas  = [];
        $inicio = $contrato->fecha_inicio->copy();
        $cargos = (int) ceil($contrato->plazo_meses / 12);

        for ($n = 0; $n < $cargos; $n++) {
            $desde = $inicio->copy()->addYears($n);
            $hasta = $desde->copy()->addYear()->subDay();

            $filas[] = $this->filaDeCargo(
                contrato: $contrato,
                numero: $n + 1,
                concepto: 'seguro',
                descripcion: sprintf(
                    'Seguro anual, del %s al %s',
                    $desde->translatedFormat('j \d\e F \d\e Y'),
                    $hasta->translatedFormat('j \d\e F \d\e Y'),
                ),
                vencimiento: $desde,
                montoTotal: (float) $contrato->monto_seguro_anual,
                tasaIva: 0,
            );
        }

        return $filas;
    }

    /**
     * C) Gastos iniciales (placas, gestoría, tenencia).
     * Un solo cargo, al firmar. También es reembolso: sin IVA desglosado.
     */
    protected function cargosIniciales(Contrato $contrato): array
    {
        if ((float) $contrato->gastos_iniciales <= 0) {
            return [];
        }

        return [
            $this->filaDeCargo(
                contrato: $contrato,
                numero: 1,
                concepto: 'gastos_iniciales',
                descripcion: $contrato->concepto_gastos_iniciales ?: 'Gastos iniciales',
                vencimiento: $contrato->fecha_inicio->copy(),
                montoTotal: (float) $contrato->gastos_iniciales,
                tasaIva: 0,
            ),
        ];
    }

    /** ¿Aplica el prorrateo del primer mes? */
    protected function prorratea(Contrato $contrato): bool
    {
        return (bool) $contrato->prorratear_primer_mes
            && $contrato->periodicidad === 'mensual'
            && (int) $contrato->fecha_inicio->day !== (int) $contrato->dia_corte;
    }

    /** "Del 17 al 31 de agosto (15 días)" */
    protected function rangoEnPalabras(\Carbon\Carbon $desde, \Carbon\Carbon $hasta, int $dias): string
    {
        return sprintf(
            'Del %s al %s (%d %s)',
            $desde->translatedFormat('j'),
            $hasta->translatedFormat('j \d\e F'),
            $dias,
            $dias === 1 ? 'día' : 'días',
        );
    }

    /**
     * Arma una fila del calendario. Todos los cargos respetan los días de
     * gracia del contrato para su fecha límite.
     */
    protected function filaDeCargo(
        Contrato $contrato,
        int $numero,
        string $concepto,
        ?string $descripcion,
        \Carbon\Carbon $vencimiento,
        float $montoTotal,
        float $tasaIva,
    ): array {
        $limite = $vencimiento->copy()->addDays($contrato->dias_gracia);

        return [
            'contrato_id'       => $contrato->id,
            'numero_exhibicion' => $numero,
            'concepto'          => $concepto,
            'descripcion'       => $descripcion,
            'fecha_vencimiento' => $vencimiento->toDateString(),
            'fecha_limite'      => $limite->toDateString(),
            'monto_sin_iva'     => $tasaIva > 0 ? round($montoTotal / (1 + $tasaIva), 2) : $montoTotal,
            'tasa_iva'          => $tasaIva,
            'monto_total'       => $montoTotal,
            'monto_aplicado'    => 0,
            'saldo'             => $montoTotal,
            'estatus'           => 'pendiente',
            'dias_atraso'       => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }

    /** Primer día de corte estrictamente posterior a la fecha dada. */
    protected function primerCorteDespuesDe(Contrato $contrato, \Carbon\Carbon $desde): \Carbon\Carbon
    {
        $corte = $desde->copy();
        $corte->day(min((int) $contrato->dia_corte, $corte->daysInMonth));

        if ($corte->lte($desde)) {
            $corte = $desde->copy()->startOfMonth()->addMonthNoOverflow();
            $corte->day(min((int) $contrato->dia_corte, $corte->daysInMonth));
        }

        return $corte;
    }

    /**
     * Desglose de lo que el cliente paga al firmar. No guarda nada:
     * sirve para enseñárselo antes de que el contrato exista.
     *
     * @return array{conceptos: array<int, array{etiqueta: string, monto: float}>, total: float, renta_mensual: float, primer_mes_completo: ?string, proximo_seguro: ?string}
     */
    public function previsualizarPrimerPago(Contrato $contrato): array
    {
        $conceptos   = [];
        $prorratea   = $this->prorratea($contrato);
        $primerCorte = $this->primerCorteDespuesDe($contrato, $contrato->fecha_inicio->copy());

        if ($prorratea) {
            $inicio    = $contrato->fecha_inicio->copy();
            $ultimoDia = $primerCorte->copy()->subDay();
            $dias      = (int) $inicio->diffInDays($ultimoDia) + 1;

            $conceptos[] = [
                'etiqueta' => 'Renta ' . lcfirst($this->rangoEnPalabras($inicio, $ultimoDia, $dias)),
                'monto'    => round((float) $contrato->renta_con_iva * $dias / $inicio->daysInMonth, 2),
            ];
        } else {
            $conceptos[] = [
                'etiqueta' => 'Primera renta mensual',
                'monto'    => (float) $contrato->renta_con_iva,
            ];
        }

        if ($contrato->cobra_seguro && (float) $contrato->monto_seguro_anual > 0) {
            $conceptos[] = ['etiqueta' => 'Seguro anual', 'monto' => (float) $contrato->monto_seguro_anual];
        }

        if ((float) $contrato->gastos_iniciales > 0) {
            $conceptos[] = [
                'etiqueta' => $contrato->concepto_gastos_iniciales ?: 'Gastos iniciales',
                'monto'    => (float) $contrato->gastos_iniciales,
            ];
        }

        if ((float) $contrato->deposito_garantia > 0) {
            $conceptos[] = ['etiqueta' => 'Depósito en garantía', 'monto' => (float) $contrato->deposito_garantia];
        }

        if ((float) $contrato->enganche > 0) {
            $conceptos[] = ['etiqueta' => 'Enganche', 'monto' => (float) $contrato->enganche];
        }

        return [
            'conceptos'           => $conceptos,
            'total'               => round(array_sum(array_column($conceptos, 'monto')), 2),
            'renta_mensual'       => (float) $contrato->renta_con_iva,
            // A partir de cuándo empieza a pagar la renta completa.
            'primer_mes_completo' => $prorratea ? $primerCorte->translatedFormat('F \d\e Y') : null,
            'proximo_seguro'      => ($contrato->cobra_seguro
                && (float) $contrato->monto_seguro_anual > 0
                && $contrato->plazo_meses > 12)
                    ? $contrato->fecha_inicio->copy()->addYear()->translatedFormat('F \d\e Y')
                    : null,
        ];
    }

    protected function numeroDePeriodos(Contrato $contrato): int
    {
        return match ($contrato->periodicidad) {
            'quincenal' => $contrato->plazo_meses * 2,
            'semanal'   => (int) ceil($contrato->plazo_meses * 4.345),
            default     => $contrato->plazo_meses,
        };
    }

    protected function fechaDeVencimiento(Contrato $contrato, int $numero): \Carbon\Carbon
    {
        $inicio = $contrato->fecha_inicio->copy();

        if ($contrato->periodicidad === 'quincenal') {
            return $inicio->addDays(15 * ($numero - 1));
        }

        if ($contrato->periodicidad === 'semanal') {
            return $inicio->addWeeks($numero - 1);
        }

        $fecha = $inicio->addMonthsNoOverflow($numero - 1);
        $dia   = min($contrato->dia_corte, $fecha->daysInMonth);

        return $fecha->day($dia);
    }

    /**
     * Recorre las exhibiciones vencidas y genera la penalización que falte.
     * Idempotente: correrlo dos veces el mismo día no duplica cargos.
     */
    public function generarPenalizacionesPorMora(Contrato $contrato): int
    {
        if ($contrato->tipo_penalizacion === 'ninguna') {
            return 0;
        }

        $generadas = 0;

        foreach ($contrato->pagosProgramados()->vencidos()->get() as $exhibicion) {
            $dias = $exhibicion->diasDeAtraso();

            if ($dias <= 0) {
                continue;
            }

            $monto = $this->calcularMora($contrato, (float) $exhibicion->saldo, $dias);

            if ($monto <= 0) {
                continue;
            }

            $existente = $contrato->penalizaciones()
                ->where('pago_programado_id', $exhibicion->id)
                ->where('tipo', 'mora')
                ->whereIn('estatus', ['pendiente', 'parcial'])
                ->first();

            if ($existente) {
                // Actualiza el cargo acumulado, no crea uno nuevo
                $existente->update([
                    'dias_atraso'  => $dias,
                    'base_calculo' => $exhibicion->saldo,
                    'monto'        => $monto,
                    'saldo'        => round($monto - (float) $existente->monto_aplicado, 2),
                ]);
                continue;
            }

            $contrato->penalizaciones()->create([
                'pago_programado_id' => $exhibicion->id,
                'tipo'               => 'mora',
                'fecha_generacion'   => now(),
                'dias_atraso'        => $dias,
                'base_calculo'       => $exhibicion->saldo,
                'monto'              => $monto,
                'saldo'              => $monto,
                'estatus'            => 'pendiente',
                'descripcion'        => "Mora exhibición {$exhibicion->numero_exhibicion}, {$dias} días",
            ]);

            $generadas++;
        }

        if ($generadas > 0 && $contrato->estatus === 'vigente') {
            $contrato->update(['estatus' => 'en_incumplimiento']);
        }

        return $generadas;
    }

    protected function calcularMora(Contrato $contrato, float $saldo, int $dias): float
    {
        $valor = (float) $contrato->valor_penalizacion;

        $monto = match ($contrato->tipo_penalizacion) {
            'porcentaje_mensual' => $saldo * $valor * ($dias / 30),
            'monto_fijo_diario'  => $valor * $dias,
            'porcentaje_unico'   => $saldo * $valor,
            default              => 0.0,
        };

        if ($contrato->tope_penalizacion) {
            $monto = min($monto, (float) $contrato->tope_penalizacion);
        }

        return round($monto, 2);
    }

        /**
     * Cierra un contrato: liquida el depósito, calcula cargos finales
     * y decide qué pasa con la unidad.
     */
    public function cerrar(Contrato $contrato, array $datos): \App\Models\CierreContrato
    {
        if (! $contrato->estaVigente() && $contrato->estatus !== 'finalizado') {
            throw new RuntimeException("No se puede cerrar un contrato en estatus «{$contrato->estatus}».");
        }

        if ($contrato->cierre) {
            throw new RuntimeException('Este contrato ya tiene un cierre registrado.');
        }

        return DB::transaction(function () use ($contrato, $datos) {
            $kmFinal = (int) ($datos['kilometraje_final'] ?? $contrato->unidad->kilometraje_actual);

            // --- Kilometraje excedente ---
            $cargoKm = 0;
            $excedente = 0;

            if ($contrato->limite_km_anual && $contrato->costo_km_excedente) {
                $anios     = max($contrato->fecha_inicio->diffInDays(now()) / 365, 0.01);
                $permitido = $contrato->limite_km_anual * $anios;
                $recorrido = $kmFinal - (int) $contrato->kilometraje_inicial;
                $excedente = max(0, (int) round($recorrido - $permitido));
                $cargoKm   = round($excedente * (float) $contrato->costo_km_excedente, 2);
            }

            // --- Lo que el arrendatario todavía debe ---
            $adeudo = $contrato->saldoTotalPendiente();

            $cargosTotales = $cargoKm
                           + (float) ($datos['cargo_daños'] ?? 0)
                           + (float) ($datos['costo_acondicionamiento'] ?? 0)
                           + $adeudo;

            // --- Depósito: se retiene contra los cargos, se devuelve el resto ---
            $deposito  = $contrato->saldoDeposito();
            $retenido  = min($deposito, $cargosTotales);
            $devuelto  = round($deposito - $retenido, 2);

            if ($retenido > 0) {
                $contrato->movimientosDeposito()->create([
                    'tipo'             => 'retencion',
                    'fecha'            => now(),
                    'monto'            => -$retenido,
                    'saldo_resultante' => round($deposito - $retenido, 2),
                    'motivo'           => 'Retención por cargos al cierre del contrato',
                    'registrado_por'   => auth()->id(),
                ]);
            }

            if ($devuelto > 0) {
                $contrato->movimientosDeposito()->create([
                    'tipo'             => 'devolucion',
                    'fecha'            => now(),
                    'monto'            => -$devuelto,
                    'saldo_resultante' => 0,
                    'motivo'           => 'Devolución de depósito en garantía',
                    'registrado_por'   => auth()->id(),
                ]);
            }

            // --- Registro del cierre ---
            $cierre = $contrato->cierre()->create([
                'tipo_cierre'               => $datos['tipo_cierre'],
                'fecha_cierre'              => $datos['fecha_cierre'] ?? now(),
                'kilometraje_final'         => $kmFinal,
                'km_excedente'              => $excedente,
                'cargo_km_excedente'        => $cargoKm,
                'costo_acondicionamiento'   => $datos['costo_acondicionamiento'] ?? 0,
                'cargo_daños'               => $datos['cargo_daños'] ?? 0,
                'adeudo_pendiente'          => $adeudo,
                'monto_venta'               => $datos['monto_venta'] ?? null,
                'comprador_nombre'          => $datos['comprador_nombre'] ?? null,
                'comprador_arrendatario_id' => $datos['tipo_cierre'] === 'venta_arrendatario'
                    ? $contrato->arrendatario_id
                    : null,
                'deposito_retenido'         => $retenido,
                'deposito_devuelto'         => $devuelto,
                'inventario_entrega'        => $datos['inventario_entrega'] ?? null,
                'observaciones'             => $datos['observaciones'] ?? null,
                'registrado_por'            => auth()->id(),
            ]);

            // --- Qué pasa con la unidad ---
            $unidad     = $contrato->unidad;
            $huboVenta  = in_array($datos['tipo_cierre'], ['venta_arrendatario', 'venta_tercero'], true);

            $unidad->update(['kilometraje_actual' => $kmFinal]);

            if ($huboVenta) {
                $unidad->update(['valor_venta_real' => $datos['monto_venta'] ?? 0]);
                $unidad->cambiarEstatus('vendida', "Venta al cierre del contrato {$contrato->folio}");
            } elseif ($datos['tipo_cierre'] === 'perdida_total') {
                $unidad->cambiarEstatus('siniestrada', "Pérdida total, contrato {$contrato->folio}");
            } else {
                $unidad->cambiarEstatus('disponible', "Devolución del contrato {$contrato->folio}");
            }

            // --- Cancela exhibiciones futuras si el cierre fue anticipado ---
            $contrato->pagosProgramados()
                ->where('estatus', 'pendiente')
                ->where('fecha_vencimiento', '>', now())
                ->update(['estatus' => 'condonado', 'saldo' => 0, 'notas' => 'Cierre anticipado del contrato']);

            $contrato->update([
                'estatus' => $datos['tipo_cierre'] === 'rescision_anticipada'
                    ? 'rescindido'
                    : 'finalizado',
            ]);

            return $cierre;
        });
    }

    /**
     * Vista previa de los números del cierre, sin guardar nada.
     * Sirve para mostrarle al arrendatario cuánto se le devuelve.
     */
    public function previsualizarCierre(Contrato $contrato, int $kmFinal): array
    {
        $cargoKm = 0;
        $excedente = 0;

        if ($contrato->limite_km_anual && $contrato->costo_km_excedente) {
            $anios     = max($contrato->fecha_inicio->diffInDays(now()) / 365, 0.01);
            $permitido = $contrato->limite_km_anual * $anios;
            $recorrido = $kmFinal - (int) $contrato->kilometraje_inicial;
            $excedente = max(0, (int) round($recorrido - $permitido));
            $cargoKm   = round($excedente * (float) $contrato->costo_km_excedente, 2);
        }

        $adeudo   = $contrato->saldoTotalPendiente();
        $deposito = $contrato->saldoDeposito();
        $cargos   = $cargoKm + $adeudo;

        return [
            'km_excedente'        => $excedente,
            'cargo_km_excedente'  => $cargoKm,
            'adeudo_pendiente'    => $adeudo,
            'deposito_disponible' => $deposito,
            'retencion_estimada'  => min($deposito, $cargos),
            'devolucion_estimada' => max(0, round($deposito - $cargos, 2)),
            'saldo_a_cargo'       => max(0, round($cargos - $deposito, 2)),
        ];
    }
}
