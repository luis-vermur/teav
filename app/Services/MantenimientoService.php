<?php

namespace App\Services;

use App\Models\Mantenimiento;
use App\Models\PagoProgramado;

class MantenimientoService
{
    /** Días que se le dan al cliente para pagar un recobro. */
    public const DIAS_PARA_PAGAR = 15;

    /**
     * Genera el cargo al cliente cuando la arrendadora adelantó un
     * mantenimiento que le corresponde pagar a él.
     *
     * Es idempotente: guardar el mismo mantenimiento dos veces no duplica
     * el cargo. La marca `[MTO-id]` dentro de la descripción es la que
     * amarra el cargo con su mantenimiento, porque `pagos_programados` no
     * tiene una llave foránea hacia `mantenimientos`.
     */
    public function generarRecobro(Mantenimiento $m): ?PagoProgramado
    {
        if (! $m->se_recobra || $m->responsable_costo !== 'arrendatario') {
            return null;
        }

        $monto = $m->costo_total;

        if ($monto <= 0) {
            return null;
        }

        // Sin contrato vigente no hay a quién cobrarle.
        $contrato = $m->contrato
            ?? $m->unidad?->contratoVigente();

        if (! $contrato) {
            return null;
        }

        $marca = "[MTO-{$m->id}]";

        $existente = PagoProgramado::where('contrato_id', $contrato->id)
            ->where('concepto', 'recobro')
            ->where('descripcion', 'like', "%{$marca}%")
            ->first();

        if ($existente) {
            // Si cambió el costo, se ajusta el cargo mientras nadie lo haya
            // pagado; si ya tiene abonos, se deja en paz.
            if ((float) $existente->monto_aplicado <= 0 && (float) $existente->monto_total !== $monto) {
                $existente->update([
                    'monto_sin_iva' => $monto,
                    'monto_total'   => $monto,
                    'saldo'         => $monto,
                    'descripcion'   => $this->descripcion($m, $marca),
                ]);
            }

            return $existente;
        }

        $vencimiento = now()->addDays(self::DIAS_PARA_PAGAR)->startOfDay();

        // El único de la tabla es (contrato_id, numero_exhibicion, concepto),
        // así que el consecutivo se lleva por concepto.
        $numero = (int) PagoProgramado::where('contrato_id', $contrato->id)
            ->where('concepto', 'recobro')
            ->max('numero_exhibicion') + 1;

        return PagoProgramado::create([
            'contrato_id'       => $contrato->id,
            'numero_exhibicion' => $numero,
            'concepto'          => 'recobro',
            'descripcion'       => $this->descripcion($m, $marca),
            'fecha_vencimiento' => $vencimiento,
            'fecha_limite'      => $vencimiento->copy()->addDays($contrato->dias_gracia),
            // Reembolso del monto exacto desembolsado: sin IVA desglosado,
            // mismo criterio que el seguro y los gastos iniciales.
            'monto_sin_iva'     => $monto,
            'tasa_iva'          => 0,
            'monto_total'       => $monto,
            'monto_aplicado'    => 0,
            'saldo'             => $monto,
            'estatus'           => 'pendiente',
            'dias_atraso'       => 0,
        ]);
    }

    /** "Servicio mayor — Taller X [MTO-12]" */
    protected function descripcion(Mantenimiento $m, string $marca): string
    {
        $partes = array_filter([
            $m->tipo?->nombre ?? 'Mantenimiento',
            $m->proveedor?->nombre,
        ]);

        return implode(' — ', $partes) . ' ' . $marca;
    }
}
