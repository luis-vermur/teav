<?php

namespace App\Policies;

use App\Models\Mantenimiento;
use App\Models\User;

/**
 * Servicios del vehículo.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class MantenimientoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_mantenimiento');
    }

    public function view(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->can('ver_mantenimiento');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_mantenimiento');
    }

    public function update(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->can('editar_mantenimiento');
    }

    public function delete(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->can('eliminar_mantenimiento');
    }
}
