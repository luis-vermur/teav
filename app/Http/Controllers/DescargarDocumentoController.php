<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescargarDocumentoController extends Controller
{
    /**
     * Entrega un documento del expediente.
     *
     * La ruta ya exige sesión y firma, pero eso sólo probaba que el enlace
     * fuera legítimo, no que ESTE usuario tuviera derecho a ver ESTE
     * documento. Con un enlace firmado, cualquiera con cuenta podía bajar
     * el INE o el acta constitutiva de cualquier cliente.
     *
     * Ahora se comprueban dos cosas:
     *   1. Que pueda ver documentos en general (`ver_documento`).
     *   2. Que pueda ver el registro del que cuelga: si no tienes acceso al
     *      cliente, tampoco a sus papeles.
     */
    public function __invoke(Documento $documento): StreamedResponse
    {
        Gate::authorize('view', $documento);

        $dueno = $documento->documentable;

        // Un documento huérfano no se entrega: sin dueño no hay contra qué
        // validar el acceso.
        abort_if($dueno === null, 404, 'El documento ya no está asociado a ningún registro.');

        Gate::authorize('view', $dueno);

        abort_unless(
            Storage::disk($documento->disco)->exists($documento->ruta),
            404,
            'El archivo ya no está disponible.'
        );

        return Storage::disk($documento->disco)->download(
            $documento->ruta,
            $documento->nombre_original
        );
    }
}
