<?php

namespace App\Policies;

use App\Models\PagoProgramado;
use App\Models\User;

/**
 * El calendario de pagos vive dentro del contrato: se rige por sus permisos.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class PagoProgramadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_contrato');
    }

    public function view(User $user, PagoProgramado $pagoProgramado): bool
    {
        return $user->can('ver_contrato');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_contrato');
    }

    public function update(User $user, PagoProgramado $pagoProgramado): bool
    {
        return $user->can('editar_contrato');
    }

    public function delete(User $user, PagoProgramado $pagoProgramado): bool
    {
        return $user->can('eliminar_contrato');
    }
}
