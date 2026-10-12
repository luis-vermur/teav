<?php

namespace App\Policies;

use App\Models\Contrato;
use App\Models\User;

class ContratoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_contrato');
    }

    public function view(User $user, Contrato $contrato): bool
    {
        return $user->can('ver_contrato');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_contrato');
    }

    public function update(User $user, Contrato $contrato): bool
    {
        return $user->can('editar_contrato');
    }

    public function delete(User $user, Contrato $contrato): bool
    {
        return $user->can('eliminar_contrato');
    }
}