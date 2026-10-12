<?php

namespace App\Policies;

use App\Models\Arrendatario;
use App\Models\User;

class ArrendatarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_arrendatario');
    }

    public function view(User $user, Arrendatario $unidad): bool
    {
        return $user->can('ver_arrendatario');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_arrendatario');
    }

    public function update(User $user, Arrendatario $unidad): bool
    {
        return $user->can('editar_arrendatario');
    }

    public function delete(User $user, Arrendatario $unidad): bool
    {
        return $user->can('eliminar_arrendatario');
    }
}