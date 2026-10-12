<?php

namespace App\Policies;

use App\Models\Penalizacion;
use App\Models\User;

/**
 * Recargos por mora. Condonarlos exige un permiso aparte.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class PenalizacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_penalizacion');
    }

    public function view(User $user, Penalizacion $penalizacion): bool
    {
        return $user->can('ver_penalizacion');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_penalizacion');
    }

    public function update(User $user, Penalizacion $penalizacion): bool
    {
        return $user->can('editar_penalizacion');
    }

    public function delete(User $user, Penalizacion $penalizacion): bool
    {
        return $user->can('eliminar_penalizacion');
    }
}
