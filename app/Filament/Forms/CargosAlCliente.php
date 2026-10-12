<?php

namespace App\Filament\Forms;

use App\Models\Contrato;
use App\Services\ContratoService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/**
 * Sección "Cargos al cliente", compartida por el formulario de cotización,
 * el de contrato y el paso 3 del asistente.
 *
 * El modelo de negocio: el seguro y los gastos de gestoría/placas los paga
 * el CLIENTE. La arrendadora los desembolsa y los recobra, así que aparecen
 * como cargos programados, no como egresos del negocio.
 */
class CargosAlCliente
{
    /**
     * @param  bool  $persistir  false en cotizaciones, donde estas columnas
     *                           no existen y los campos sólo sirven para
     *                           previsualizar el primer pago.
     */
    public static function seccion(bool $persistir = true): Section
    {
        return Section::make('Cargos al cliente')
            ->description('Además de la renta: seguro y gastos que la arrendadora desembolsa y recobra')
            ->icon('heroicon-o-receipt-percent')
            ->schema([
                Toggle::make('prorratear_primer_mes')
                    ->label('Cobrar sólo los días del primer mes')
                    ->default(true)
                    ->live()
                    ->dehydrated($persistir)
                    ->helperText('Si el contrato empieza a media quincena, la primera exhibición cubre sólo los días transcurridos hasta el corte, no el mes completo.')
                    ->columnSpanFull(),

                Toggle::make('cobra_seguro')
                    ->label('El cliente paga el seguro')
                    ->default(true)
                    ->live()
                    ->dehydrated($persistir)
                    ->helperText('Apágalo sólo si la arrendadora absorbe la póliza: entonces sí cuenta como egreso.')
                    ->columnSpanFull(),

                TextInput::make('monto_seguro_anual')
                    ->label('Seguro anual')
                    ->numeric()->prefix('$')->default(0)
                    ->live(onBlur: true)
                    ->dehydrated($persistir)
                    ->helperText('Se cobra cada 12 meses durante el contrato')
                    ->visible(fn (Get $get) => (bool) $get('cobra_seguro')),

                TextInput::make('gastos_iniciales')
                    ->label('Gastos iniciales')
                    ->numeric()->prefix('$')->default(0)
                    ->live(onBlur: true)
                    ->dehydrated($persistir)
                    ->helperText('Una sola vez, al firmar'),

                TextInput::make('concepto_gastos_iniciales')
                    ->label('Concepto de los gastos')
                    ->placeholder('Placas, gestoría, tenencia')
                    ->dehydrated($persistir)
                    ->columnSpanFull(),

                Placeholder::make('desglose_primer_pago')
                    ->label('')
                    ->content(fn (Get $get) => new HtmlString(self::desglose($get)))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Desglose de lo que el cliente paga al firmar.
     *
     * Se arma un Contrato en memoria —sin guardarlo— para poder reutilizar
     * ContratoService::previsualizarPrimerPago() en lugar de repetir aquí
     * la aritmética del prorrateo.
     */
    public static function desglose(Get $get): string
    {
        $renta = (float) $get('renta_con_iva');
        $plazo = (int) $get('plazo_meses');

        if ($renta <= 0 || $plazo <= 0) {
            return "<p style='color:#6b7280;font-size:14px'>Captura la renta y el plazo para ver el primer pago.</p>";
        }

        $inicio = $get('fecha_inicio') ?: $get('fecha_inicio_estimada');

        if (blank($inicio)) {
            return "<p style='color:#6b7280;font-size:14px'>Captura la fecha de inicio para ver el desglose del primer pago.</p>";
        }

        $contrato = new Contrato([
            'fecha_inicio'              => \Carbon\Carbon::parse($inicio),
            'dia_corte'                 => (int) ($get('dia_corte') ?: 1),
            'periodicidad'              => $get('periodicidad') ?: 'mensual',
            'renta_con_iva'             => $renta,
            'plazo_meses'               => $plazo,
            'prorratear_primer_mes'     => (bool) $get('prorratear_primer_mes'),
            'cobra_seguro'              => (bool) $get('cobra_seguro'),
            'monto_seguro_anual'        => (float) $get('monto_seguro_anual'),
            'gastos_iniciales'          => (float) $get('gastos_iniciales'),
            'concepto_gastos_iniciales' => $get('concepto_gastos_iniciales'),
            'deposito_garantia'         => (float) $get('deposito_garantia'),
            'enganche'                  => (float) $get('enganche'),
        ]);

        $p = app(ContratoService::class)->previsualizarPrimerPago($contrato);
        $f = fn ($n) => '$' . number_format($n, 2);

        $filas = '';

        foreach ($p['conceptos'] as $c) {
            $filas .= "<tr>"
                . "<td style='padding:3px 0'>{$c['etiqueta']}</td>"
                . "<td style='padding:3px 0;text-align:right;font-variant-numeric:tabular-nums'>{$f($c['monto'])}</td>"
                . '</tr>';
        }

        $pie = '';

        if ($p['primer_mes_completo']) {
            $pie .= "A partir de {$p['primer_mes_completo']}: <b>{$f($p['renta_mensual'])}</b> mensuales<br>";
        }

        if ($p['proximo_seguro']) {
            $pie .= "Próximo cargo de seguro: <b>{$p['proximo_seguro']}</b>";
        }

        return "<div style='font-size:14px;line-height:1.7'>"
            . "<div style='font-weight:600;margin-bottom:6px'>El cliente paga al firmar:</div>"
            . "<table style='width:100%;max-width:460px'>{$filas}"
            . "<tr><td colspan='2' style='border-top:1px solid rgba(184,145,47,.4);padding-top:6px'></td></tr>"
            . "<tr style='font-weight:700'>"
            . "<td style='padding:3px 0'>Total</td>"
            . "<td style='padding:3px 0;text-align:right;font-variant-numeric:tabular-nums'>{$f($p['total'])}</td>"
            . '</tr></table>'
            . ($pie ? "<div style='margin-top:10px;color:#6b7280'>{$pie}</div>" : '')
            . '</div>';
    }
}
