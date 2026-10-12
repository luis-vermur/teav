<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PagoService
{
    /**
     * Registra un pago y lo aplica automáticamente.
     *
     * Orden de prelación: primero penalizaciones más viejas,
     * luego exhibiciones más viejas. Es el estándar de cobranza:
     * si se aplicara primero a la renta corriente, las moras
     * quedarían vivas generando más intereses.
     */
    public function registrar(Contrato $contrato, array $datos): Pago
    {
        return DB::transaction(function () use ($contrato, $datos) {
            $pago = $contrato->pagos()->create([
                'arrendatario_id' => $contrato->arrendatario_id,
                'fecha_pago'      => $datos['fecha_pago'] ?? now(),
                'monto'           => $datos['monto'],
                'metodo_pago'     => $datos['metodo_pago'],
                'referencia'      => $datos['referencia'] ?? null,
                'cuenta_destino'  => $datos['cuenta_destino'] ?? null,
                'banco_origen'    => $datos['banco_origen'] ?? null,
                'notas'           => $datos['notas'] ?? null,
                'estatus'         => 'registrado',
                'registrado_por'  => auth()->id(),
            ]);

            $this->aplicar($pago);

            return $pago->fresh(['aplicaciones']);
        });
    }

    public function aplicar(Pago $pago): void
    {
        $disponible = $pago->montoDisponible();

        if ($disponible <= 0) {
            return;
        }

        $contrato = $pago->contrato;

        // 1) Penalizaciones pendientes, de la más antigua a la más nueva
        foreach ($contrato->penalizaciones()->pendientes()->orderBy('fecha_generacion')->get() as $pen) {
            if ($disponible <= 0) {
                break;
            }

            $aplicar = min($disponible, (float) $pen->saldo);

            $pago->aplicaciones()->create([
                'penalizacion_id' => $pen->id,
                'concepto'        => 'penalizacion',
                'monto'           => $aplicar,
            ]);

            $pen->recalcular();
            $disponible = round($disponible - $aplicar, 2);
        }

        /**
         * 2) Exhibiciones pendientes, de la más antigua a la más nueva.
         *
         * Ordena por FECHA primero: ahora un mismo numero_exhibicion puede
         * tener varias filas con distinto concepto (la renta del mes y el
         * seguro de ese año), y el número por sí solo ya no marca el orden
         * cronológico. El número queda como desempate estable.
         */
        foreach (
            $contrato->pagosProgramados()
                ->pendientes()
                // reorder() descarta el orden que trae la relación; sin esto
                // aquél se aplicaba primero y éste quedaba de adorno.
                ->reorder()
                ->orderBy('fecha_vencimiento')
                ->orderBy('numero_exhibicion')
                ->get() as $exhibicion
        ) {
            if ($disponible <= 0) {
                break;
            }

            $aplicar = min($disponible, (float) $exhibicion->saldo);

            $pago->aplicaciones()->create([
                'pago_programado_id' => $exhibicion->id,
                'concepto'           => 'renta',
                'monto'              => $aplicar,
            ]);

            $exhibicion->recalcular();
            $disponible = round($disponible - $aplicar, 2);
        }

        $pago->update([
            'monto_aplicado' => round((float) $pago->monto - $disponible, 2),
            'saldo_a_favor'  => $disponible,   // sobrante para el siguiente periodo
            'estatus'        => 'aplicado',
        ]);

        $this->actualizarEstatusContrato($contrato);
    }

    /** Deshace un pago mal capturado sin borrar el rastro. */
    public function reversar(Pago $pago, string $motivo): void
    {
        if ($pago->estatus === 'reversado') {
            throw new RuntimeException('Este pago ya fue reversado.');
        }

        DB::transaction(function () use ($pago, $motivo) {
            $exhibiciones  = $pago->aplicaciones()->whereNotNull('pago_programado_id')->pluck('pago_programado_id');
            $penalizaciones = $pago->aplicaciones()->whereNotNull('penalizacion_id')->pluck('penalizacion_id');

            $pago->aplicaciones()->delete();

            \App\Models\PagoProgramado::whereIn('id', $exhibiciones)->get()
                ->each->recalcular();

            \App\Models\Penalizacion::whereIn('id', $penalizaciones)->get()
                ->each->recalcular();

            $pago->update([
                'estatus'        => 'reversado',
                'motivo_reverso' => $motivo,
                'monto_aplicado' => 0,
                'saldo_a_favor'  => 0,
            ]);

            $this->actualizarEstatusContrato($pago->contrato);
        });
    }

    protected function actualizarEstatusContrato(Contrato $contrato): void
    {
        if (! $contrato->estaVigente()) {
            return;
        }

        $nuevo = match (true) {
            $contrato->saldoVencido() > 0        => 'en_incumplimiento',
            $contrato->diasParaVencer() <= 60    => 'por_vencer',
            default                              => 'vigente',
        };

        if ($contrato->estatus !== $nuevo) {
            $contrato->update(['estatus' => $nuevo]);
        }
    }
}