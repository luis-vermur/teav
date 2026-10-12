<?php

namespace App\Policies;

use App\Models\Cotizacion;
use App\Models\User;

class CotizacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_cotizacion');
    }

    public function view(User $user, Cotizacion $unidad): bool
    {
        return $user->can('ver_cotizacion');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_cotizacion');
    }

    public function update(User $user, Cotizacion $unidad): bool
    {
        return $user->can('editar_cotizacion');
    }

    public function delete(User $user, Cotizacion $unidad): bool
    {
        return $user->can('eliminar_cotizacion');
    }
}