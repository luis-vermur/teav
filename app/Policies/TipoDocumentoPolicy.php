<?php

namespace App\Policies;

use App\Models\TipoDocumento;
use App\Models\User;

/**
 * Catálogo: tipos de documento del expediente.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class TipoDocumentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_catalogo');
    }

    public function view(User $user, TipoDocumento $tipoDocumento): bool
    {
        return $user->can('ver_catalogo');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_catalogo');
    }

    public function update(User $user, TipoDocumento $tipoDocumento): bool
    {
        return $user->can('editar_catalogo');
    }

    public function delete(User $user, TipoDocumento $tipoDocumento): bool
    {
        return $user->can('eliminar_catalogo');
    }
}
