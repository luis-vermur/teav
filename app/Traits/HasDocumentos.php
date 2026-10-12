<?php

namespace App\Traits;

use App\Models\Documento;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocumentos
{
    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    /** Sólo la versión vigente de cada documento. */
    public function documentosVigentes(): MorphMany
    {
        return $this->documentos()->where('vigente', true);
    }

    /** Los tipos de documento que aplican a esta entidad. */
    public function tiposDocumentoAplicables()
    {
        return \App\Models\TipoDocumento::query()
            ->where('aplica_a', static::class)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /** Claves de los documentos obligatorios que aún faltan. */
    public function documentosFaltantes(): array
    {
        $subidos = $this->documentosVigentes()
            ->pluck('tipo_documento_id')
            ->all();

        return $this->tiposDocumentoAplicables()
            ->where('es_obligatorio', true)
            ->whereNotIn('id', $subidos)
            ->pluck('nombre')
            ->all();
    }

    public function tieneDocumentacionCompleta(): bool
    {
        return empty($this->documentosFaltantes());
    }
}