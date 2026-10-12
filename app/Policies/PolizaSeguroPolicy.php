<?php

namespace App\Policies;

use App\Models\PolizaSeguro;
use App\Models\User;

/**
 * Pólizas de seguro de la flota.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class PolizaSeguroPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_poliza');
    }

    public function view(User $user, PolizaSeguro $polizaSeguro): bool
    {
        return $user->can('ver_poliza');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_poliza');
    }

    public function update(User $user, PolizaSeguro $polizaSeguro): bool
    {
        return $user->can('editar_poliza');
    }

    public function delete(User $user, PolizaSeguro $polizaSeguro): bool
    {
        return $user->can('eliminar_poliza');
    }
}
