<?php

namespace Tests\Unit;

use App\Services\CotizacionService;
use Tests\TestCase;

/**
 * Despeje de la renta a partir del rendimiento deseado.
 *
 * Es el cálculo del que cuelga toda la operación comercial: si se desvía,
 * se cotiza mal y el error viaja al contrato y al calendario de pagos.
 *
 * No toca base de datos: son fórmulas puras.
 */
class CalculoDeRentaTest extends TestCase
{
    protected CotizacionService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(CotizacionService::class);
    }

    /**
     * El caso que validó el negocio: un coche de 736,500 a 48 meses con
     * venta estimada de 449,500 y 29.95% anual debe cobrarse en 24,400.
     */
    public function test_el_caso_de_referencia_del_negocio(): void
    {
        $r = $this->servicio->rentaParaRendimiento(736500, 'anual', 0.2995, 48, 449500);

        $this->assertTrue($r['viable']);
        $this->assertSame(24400.0, $r['renta_con_iva']);
        $this->assertSame(1171200.0, $r['renta_total']);      // 24,400 × 48
        $this->assertSame(1620700.0, $r['ingreso_bruto']);    // + venta final
    }

    /** La renta se redondea SIEMPRE hacia arriba, a la centena. */
    public function test_redondea_hacia_arriba_a_la_centena(): void
    {
        $r = $this->servicio->rentaParaRendimiento(736500, 'anual', 0.2995, 48, 449500);

        // El despeje exacto da 24,360.98; hacia arriba son 24,400.
        $this->assertSame(24400.0, $r['renta_con_iva']);
        $this->assertSame(0.0, fmod($r['renta_con_iva'], 100), 'Debe quedar en centena exacta');
    }

    /**
     * Redondear hacia arriba nunca puede dejar el rendimiento por debajo
     * de lo pedido: ése es el motivo de redondear en esa dirección.
     */
    public function test_el_rendimiento_real_nunca_queda_bajo_el_pedido(): void
    {
        foreach ([0.15, 0.2995, 0.35, 0.50] as $objetivo) {
            $r = $this->servicio->rentaParaRendimiento(736500, 'anual', $objetivo, 48, 449500);

            $this->assertGreaterThanOrEqual(
                $objetivo,
                $r['rendimiento_anual'],
                "Con objetivo {$objetivo} el rendimiento real quedó por debajo",
            );
        }
    }

    /** El criterio "sólo rentas" ignora por completo la venta final. */
    public function test_el_criterio_por_rentas_ignora_la_venta(): void
    {
        $sinVenta = $this->servicio->rentaParaRendimiento(736500, 'renta', 0.30, 48, 0);
        $conVenta = $this->servicio->rentaParaRendimiento(736500, 'renta', 0.30, 48, 449500);

        $this->assertSame($sinVenta['renta_con_iva'], $conVenta['renta_con_iva']);
    }

    /** Objetivos equivalentes por los tres criterios dan la misma renta. */
    public function test_los_tres_criterios_convergen(): void
    {
        $anual = $this->servicio->rentaParaRendimiento(736500, 'anual', 0.2995, 48, 449500);
        $total = $this->servicio->rentaParaRendimiento(736500, 'total', 1.198, 48, 449500);

        $this->assertSame($anual['renta_con_iva'], $total['renta_con_iva']);
    }

    /**
     * Si la venta estimada por sí sola ya supera el objetivo, no hay renta
     * positiva posible. Debe avisarlo, no devolver un número absurdo.
     */
    public function test_avisa_cuando_la_venta_ya_supera_el_objetivo(): void
    {
        $r = $this->servicio->rentaParaRendimiento(736500, 'total', 0.10, 48, 900000);

        $this->assertFalse($r['viable']);
        $this->assertSame(0.0, $r['renta_con_iva']);
        $this->assertStringContainsString('valor de venta', $r['motivo']);
    }

    /** Sin precio o sin plazo no se puede despejar (el plazo es divisor). */
    public function test_se_protege_de_precio_y_plazo_en_cero(): void
    {
        $this->assertFalse($this->servicio->rentaParaRendimiento(0, 'anual', 0.30, 48, 0)['viable']);
        $this->assertFalse($this->servicio->rentaParaRendimiento(736500, 'anual', 0.30, 0, 0)['viable']);
    }

    /**
     * El denominador de los rendimientos es el precio de compra, NO la
     * inversión con gastos. Se comprueba con la fórmula al derecho.
     */
    public function test_el_rendimiento_se_mide_sobre_el_precio_de_compra(): void
    {
        $precio = 736500;
        $r = $this->servicio->rentaParaRendimiento($precio, 'anual', 0.2995, 48, 449500);

        $esperado = ($r['ingreso_bruto'] / $precio) - 1;

        $this->assertEqualsWithDelta($esperado, $r['rendimiento_total'], 0.000001);
    }
}
