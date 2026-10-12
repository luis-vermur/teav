<?php

namespace App\Policies;

use App\Models\User;

/**
 * Cuentas del sistema.
 *
 * Sin ver_usuario, "Usuarios" desaparece del menú. El rol operador no
 * administra cuentas.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_usuario');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('ver_usuario');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_usuario');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('editar_usuario');
    }

    public function delete(User $user, User $model): bool
    {
        // Nadie se borra a sí mismo.
        return $user->can('eliminar_usuario') && $user->id !== $model->id;
    }
}
