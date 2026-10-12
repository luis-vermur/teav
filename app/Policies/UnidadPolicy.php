<?php

namespace App\Policies;

use App\Models\Unidad;
use App\Models\User;

class UnidadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_unidad');
    }

    public function view(User $user, Unidad $unidad): bool
    {
        return $user->can('ver_unidad');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_unidad');
    }

    public function update(User $user, Unidad $unidad): bool
    {
        return $user->can('editar_unidad');
    }

    public function delete(User $user, Unidad $unidad): bool
    {
        return $user->can('eliminar_unidad');
    }
}