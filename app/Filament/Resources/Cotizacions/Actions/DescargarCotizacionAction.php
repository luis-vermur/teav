<?php

namespace App\Filament\Resources\Cotizacions\Actions;

use App\Models\Cotizacion;
use App\Services\CotizacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Response;

/**
 * Genera el documento comercial de la cotización en PDF.
 *
 * Se define una sola vez y se reutiliza en la tabla y en la página de
 * detalle, para que el botón se comporte igual en los dos lugares.
 */
class DescargarCotizacionAction
{
    public static function make(string $nombre = 'descargar_pdf'): Action
    {
        return Action::make($nombre)
            ->label('Descargar PDF')
            ->icon('heroicon-m-document-arrow-down')
            ->color('gray')
            // Sin renta o sin plazo el documento saldría en ceros.
            ->visible(fn (Cotizacion $record) => (float) $record->renta_con_iva > 0
                && $record->plazo_meses > 0)
            ->action(function (Cotizacion $record) {
                $datos = app(CotizacionService::class)->datosParaDocumento($record);

                $pdf = Pdf::loadView('documentos.cotizacion', $datos)->setPaper('letter');

                return Response::streamDownload(
                    fn () => print($pdf->output()),
                    "Cotizacion-{$record->folio}.pdf",
                );
            });
    }
}
