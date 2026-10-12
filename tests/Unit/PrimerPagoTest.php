<?php

namespace Tests\Unit;

use App\Models\Contrato;
use App\Services\ContratoService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Desglose de lo que el cliente paga al firmar.
 *
 * El prorrateo del primer mes es la parte más fácil de romper del sistema:
 * depende de días, de meses de distinta duración y del día de corte.
 *
 * No toca base de datos: se arma un Contrato en memoria.
 */
class PrimerPagoTest extends TestCase
{
    protected ContratoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(ContratoService::class);
        Carbon::setLocale('es');
    }

    /** Contrato de ejemplo, con los valores que validó el negocio. */
    protected function contrato(array $extra = []): Contrato
    {
        return new Contrato(array_merge([
            'fecha_inicio'              => Carbon::parse('2026-08-17'),
            'dia_corte'                 => 1,
            'periodicidad'              => 'mensual',
            'renta_con_iva'             => 24400,
            'renta_sin_iva'             => 21034.48,
            'tasa_iva'                  => 0.16,
            'plazo_meses'               => 48,
            'prorratear_primer_mes'     => true,
            'cobra_seguro'              => true,
            'monto_seguro_anual'        => 28400,
            'gastos_iniciales'          => 18500,
            'concepto_gastos_iniciales' => 'Placas y gestoría',
            'deposito_garantia'         => 24400,
            'enganche'                  => 0,
            'dias_gracia'               => 5,
        ], $extra));
    }

    /** El caso de referencia: 83,106.45 al firmar. */
    public function test_el_caso_de_referencia_del_negocio(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato());

        $this->assertEqualsWithDelta(83106.45, $p['total'], 0.01);
        $this->assertCount(4, $p['conceptos']);
    }

    /**
     * Del 17 al 31 de agosto son 15 días de un mes de 31:
     * 24,400 × 15/31 = 11,806.45
     */
    public function test_prorratea_por_los_dias_realmente_usados(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato());
        $renta = $p['conceptos'][0];

        $this->assertEqualsWithDelta(11806.45, $renta['monto'], 0.01);
        $this->assertStringContainsString('15 días', $renta['etiqueta']);
    }

    /** Febrero tiene 28 días: el divisor cambia con el mes. */
    public function test_usa_los_dias_del_mes_de_inicio(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato([
            'fecha_inicio' => Carbon::parse('2026-02-15'),
        ]));

        // Del 15 al 28 de febrero: 14 días de 28 = exactamente media renta.
        $this->assertEqualsWithDelta(12200.0, $p['conceptos'][0]['monto'], 0.01);
        $this->assertStringContainsString('14 días', $p['conceptos'][0]['etiqueta']);
    }

    /** Si el contrato arranca justo en el día de corte, no hay prorrateo. */
    public function test_sin_prorrateo_cuando_inicia_en_el_dia_de_corte(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato([
            'fecha_inicio' => Carbon::parse('2026-09-01'),
            'dia_corte'    => 1,
        ]));

        $this->assertSame(24400.0, $p['conceptos'][0]['monto']);
        $this->assertStringContainsString('Primera renta', $p['conceptos'][0]['etiqueta']);
    }

    /** Con el prorrateo apagado se cobra el mes completo. */
    public function test_respeta_el_interruptor_de_prorrateo(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato([
            'prorratear_primer_mes' => false,
        ]));

        $this->assertSame(24400.0, $p['conceptos'][0]['monto']);
    }

    /** Sin cobro de seguro, ese concepto desaparece del desglose. */
    public function test_omite_el_seguro_cuando_no_se_cobra(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato([
            'cobra_seguro' => false,
        ]));

        $etiquetas = array_column($p['conceptos'], 'etiqueta');

        $this->assertNotContains('Seguro anual', $etiquetas);
        $this->assertEqualsWithDelta(83106.45 - 28400, $p['total'], 0.01);
    }

    /** El total siempre es la suma de sus partes. */
    public function test_el_total_cuadra_con_los_conceptos(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato());

        $this->assertEqualsWithDelta(
            array_sum(array_column($p['conceptos'], 'monto')),
            $p['total'],
            0.01,
        );
    }

    /** Dice desde cuándo se paga la renta completa y cuándo toca el seguro. */
    public function test_informa_el_siguiente_mes_y_el_proximo_seguro(): void
    {
        $p = $this->servicio->previsualizarPrimerPago($this->contrato());

        $this->assertStringContainsString('septiembre', $p['primer_mes_completo']);
        $this->assertStringContainsString('agosto', $p['proximo_seguro']);
    }
}
