<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

/**
 * Expediente de clientes, vehículos y contratos.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class DocumentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_documento');
    }

    public function view(User $user, Documento $documento): bool
    {
        return $user->can('ver_documento');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_documento');
    }

    public function update(User $user, Documento $documento): bool
    {
        return $user->can('editar_documento');
    }

    public function delete(User $user, Documento $documento): bool
    {
        return $user->can('eliminar_documento');
    }
}
