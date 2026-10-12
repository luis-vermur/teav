{{--
    Documento comercial de cotización — Arrendadora TEAV.

    Replica el formato impreso, pero SÓLO con los conceptos que el sistema
    maneja de verdad: primer pago (con el mes proporcional), renta mensual,
    seguro anual y gastos de gestoría/placas.

    Deliberadamente NO incluye CAT, comisión por apertura, anticipo a rentas
    ni seguros financiados: son de un producto de crédito, no de este
    arrendamiento. Imprimir esas líneas en blanco o con números inventados
    sería peor que omitirlas.

    Ojo con dompdf: nada de flexbox ni grid. Todo va con tablas.
--}}
@php
    $f  = fn ($n) => '$' . number_format((float) $n, 2);
    $v  = $vehiculo;
    $pp = $primer_pago;
    $logo = public_path('images/logo-teav.png');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->folio }}</title>
    <style>
        @page { margin: 28px 34px 46px 34px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;   /* soporta acentos y ñ */
            font-size: 9.5px;
            color: #1a1a1a;
            margin: 0;
        }

        .oro      { color: #b8912f; }
        .tinta    { color: #08080a; }
        .der      { text-align: right; }
        .num      { text-align: right; white-space: nowrap; }
        .b        { font-weight: bold; }

        h1 { font-size: 21px; margin: 0; letter-spacing: -0.5px; }
        h2 { font-size: 15px; margin: 18px 0 6px 0; letter-spacing: -0.3px; }

        /* Barra dorada a la izquierda de cada bloque, como el formato */
        .bloque {
            border-left: 3px solid #b8912f;
            padding-left: 9px;
        }
        .bloque .titulo {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 4px;
        }
        .linea { margin: 2.5px 0; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3.5px 0; vertical-align: top; }

        .tabla-cargos th {
            border-bottom: 1.5px solid #08080a;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .tabla-cargos td { border-bottom: 1px solid #eee; }

        .total td {
            border-top: 1.5px solid #b8912f;
            border-bottom: none;
            font-weight: bold;
            font-size: 11px;
            padding-top: 6px;
        }

        .pie {
            position: fixed;
            bottom: -30px; left: 0; right: 0;
            font-size: 7.5px; color: #777;
        }
        .legal { font-size: 7.5px; color: #555; line-height: 1.45; margin-top: 26px; }
        .marca-agua { height: 34px; }
        .sep { border: none; border-top: 1px solid #ddd; margin: 14px 0; }
    </style>
</head>
<body>

{{-- ════════════ PÁGINA 1 ════════════ --}}
<div class="pie">Pág. 1/2 &nbsp;·&nbsp; {{ $cotizacion->folio }}</div>

<table>
    <tr>
        <td style="width:33%">
            @if (file_exists($logo))
                <img src="{{ $logo }}" class="marca-agua" alt="TEAV">
            @else
                <span class="b tinta" style="font-size:19px">ARRENDADORA TEAV</span>
            @endif
        </td>
        <td style="width:34%; text-align:center">
            <h1>ARRENDAMIENTO</h1>
            <div style="color:#555">Plan: {{ $cotizacion->proposito === 'comercial' ? 'Propuesta comercial' : 'Análisis interno' }}</div>
        </td>
        <td style="width:33%"></td>
    </tr>
</table>

<hr class="sep">

<table style="margin-bottom:6px">
    <tr>
        <td><span class="b">ID Cotización:</span> &nbsp; {{ $cotizacion->folio }}</td>
        <td class="der">
            <span class="b">Vigencia:</span> &nbsp;
            {{ $cotizacion->vigencia_hasta?->format('d/m/Y') ?? '—' }}
        </td>
    </tr>
</table>

<p style="margin:10px 0 4px 0">
    @if ($destinatario)
        Estimado(a) <span class="b">{{ $destinatario }}</span>:
    @else
        Distinguido cliente:
    @endif
</p>
<p style="margin:0 0 12px 0">
    Con <span class="b">Arrendadora TEAV</span> obtienes un plan de arrendamiento con mensualidades
    fijas y el seguro del vehículo incluido en tu pago.
</p>

<table>
    <tr>
        <td style="width:48%; padding-right:14px">
            <div class="bloque">
                <div class="titulo">Características</div>
                <div class="linea"><span class="b">Marca:</span> {{ $v['marca'] ?? '—' }}</div>
                <div class="linea"><span class="b">Submarca:</span> {{ $v['submarca'] ?? '—' }}</div>
                <div class="linea"><span class="b">Versión:</span> {{ $v['version'] ?: '—' }}</div>
                <div class="linea"><span class="b">Año:</span> {{ $v['anio'] ?: '—' }}</div>
                <div class="linea"><span class="b">Clasificación:</span> {{ $v['clasificacion'] ?: '—' }}</div>
                <div class="linea"><span class="b">Color:</span> {{ $v['color'] ?: '—' }}</div>
            </div>
        </td>
        <td style="width:52%">
            <div class="bloque">
                <div class="titulo">Detalles</div>
                <div class="linea"><span class="b">Precio:</span> {{ $f($cotizacion->precio_adquisicion) }}</div>
                <div class="linea"><span class="b">Plazo:</span> {{ $cotizacion->plazo_meses }} meses</div>
                <div class="linea" style="margin-top:8px">
                    <span class="b" style="font-size:13px">Renta mensual con IVA</span><br>
                    <span class="b oro" style="font-size:17px">{{ $f($cotizacion->renta_con_iva) }}</span>
                </div>
            </div>
        </td>
    </tr>
</table>

<h2>RESUMEN DEL PLAN</h2>

<table class="tabla-cargos">
    <tr>
        <th style="text-align:left">Concepto</th>
        <th class="num oro">{{ $cotizacion->plazo_meses }} MESES</th>
    </tr>
    <tr>
        <td>Pago inicial total</td>
        <td class="num">{{ $f($pp['total']) }}</td>
    </tr>
    <tr>
        <td>Pago mensual total con IVA</td>
        <td class="num">{{ $f($cotizacion->renta_con_iva) }}</td>
    </tr>
    @if ($cotizacion->cobra_seguro && (float) $cotizacion->monto_seguro_anual > 0)
        <tr>
            <td>Seguro anual <span style="color:#777">(se cobra cada 12 meses)</span></td>
            <td class="num">{{ $f($cotizacion->monto_seguro_anual) }}</td>
        </tr>
    @endif
    @if ((float) $cotizacion->gastos_iniciales > 0)
        <tr>
            <td>{{ $cotizacion->concepto_gastos_iniciales ?: 'Gastos iniciales' }} <span style="color:#777">(una sola vez)</span></td>
            <td class="num">{{ $f($cotizacion->gastos_iniciales) }}</td>
        </tr>
    @endif
</table>

@if ($concesionaria)
    <div class="bloque" style="margin-top:20px">
        <div class="titulo">Contacto</div>
        <div class="linea"><span class="b">Concesionaria:</span> {{ $concesionaria->nombre }}</div>
        @if ($concesionaria->telefono)
            <div class="linea"><span class="b">Teléfono:</span> {{ $concesionaria->telefono }}</div>
        @endif
        @if ($concesionaria->contacto_nombre)
            <div class="linea"><span class="b">Contacto:</span> {{ $concesionaria->contacto_nombre }}</div>
        @endif
        @if ($concesionaria->email)
            <div class="linea"><span class="b">Email:</span> {{ $concesionaria->email }}</div>
        @endif
    </div>
@endif

{{-- ════════════ PÁGINA 2 ════════════ --}}
<div style="page-break-before: always;"></div>
<div class="pie">Pág. 2/2 &nbsp;·&nbsp; {{ $cotizacion->folio }}</div>

<table style="margin-bottom:4px">
    <tr>
        <td style="color:#555">ID Cotización: {{ $cotizacion->folio }}</td>
        <td class="der" style="color:#555">
            Vigente al: {{ $cotizacion->vigencia_hasta?->format('d/m/Y') ?? '—' }}
        </td>
    </tr>
</table>

<h1 style="font-size:18px">DESGLOSE GENERAL</h1>
<hr class="sep">

<h2>INTEGRACIÓN DEL PAGO INICIAL</h2>

<table class="tabla-cargos">
    @foreach ($pp['conceptos'] as $c)
        <tr>
            <td>(+) {{ $c['etiqueta'] }}</td>
            <td class="num">{{ $f($c['monto']) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td>(=) Total pago inicial</td>
        <td class="num">{{ $f($pp['total']) }}</td>
    </tr>
</table>

<h2>DESGLOSE DE LA RENTA MENSUAL</h2>

<table class="tabla-cargos">
    <tr>
        <td>(+) Renta mensual del vehículo sin IVA</td>
        <td class="num">{{ $f($cotizacion->renta_sin_iva) }}</td>
    </tr>
    <tr>
        <td>(+) IVA ({{ number_format((float) $cotizacion->tasa_iva * 100, 0) }}%)</td>
        <td class="num">{{ $f((float) $cotizacion->renta_con_iva - (float) $cotizacion->renta_sin_iva) }}</td>
    </tr>
    <tr class="total">
        <td>(=) Total pago mensual con IVA</td>
        <td class="num">{{ $f($cotizacion->renta_con_iva) }}</td>
    </tr>
</table>

@if ($pp['primer_mes_completo'] || $pp['proximo_seguro'])
    <div class="bloque" style="margin-top:18px">
        <div class="titulo">Cómo se pagan los siguientes meses</div>
        @if ($pp['primer_mes_completo'])
            <div class="linea">
                A partir de <span class="b">{{ $pp['primer_mes_completo'] }}</span> se paga la renta
                completa de <span class="b">{{ $f($pp['renta_mensual']) }}</span> cada mes,
                el día {{ $cotizacion->dia_corte }}.
            </div>
        @endif
        @if ($pp['proximo_seguro'])
            <div class="linea">
                El seguro se recobra cada 12 meses. Próximo cargo:
                <span class="b">{{ $pp['proximo_seguro'] }}</span>.
            </div>
        @endif
    </div>
@endif

<div class="legal">
    <span class="b">Arrendadora TEAV</span> es la entidad comercial que emite el presente documento.
    La presente cotización no forma parte integrante del contrato de arrendamiento que se llegase a
    celebrar y está sujeta a la aprobación correspondiente. Todas las cantidades están expresadas en
    moneda nacional e incluyen IVA salvo donde se indique lo contrario. La presente simulación se
    emite conforme a la oferta vigente y será modificada si cambian las condiciones de contratación.
    El seguro del vehículo es requerido durante toda la vigencia del contrato; la arrendadora lo
    contrata y lo recobra al arrendatario en los términos aquí señalados, sin cargo financiero
    adicional. La cobertura es responsabilidad de la compañía aseguradora que la emite.
</div>

</body>
</html>
