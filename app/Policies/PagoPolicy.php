<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\User;

class PagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_pago');
    }

    public function view(User $user, Pago $unidad): bool
    {
        return $user->can('ver_pago');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_pago');
    }

    public function update(User $user, Pago $unidad): bool
    {
        return $user->can('editar_pago');
    }

    public function delete(User $user, Pago $unidad): bool
    {
        return $user->can('eliminar_pago');
    }
}