<?php

namespace App\Policies;

use App\Models\AplicacionPago;
use App\Models\User;

/**
 * A qué exhibición se aplicó cada peso de un pago.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class AplicacionPagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_pago');
    }

    public function view(User $user, AplicacionPago $aplicacionPago): bool
    {
        return $user->can('ver_pago');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_pago');
    }

    public function update(User $user, AplicacionPago $aplicacionPago): bool
    {
        return $user->can('editar_pago');
    }

    public function delete(User $user, AplicacionPago $aplicacionPago): bool
    {
        return $user->can('eliminar_pago');
    }
}
