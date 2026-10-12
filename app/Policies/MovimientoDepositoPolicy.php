<?php

namespace App\Policies;

use App\Models\MovimientoDeposito;
use App\Models\User;

/**
 * Movimientos del depósito en garantía del contrato.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class MovimientoDepositoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_contrato');
    }

    public function view(User $user, MovimientoDeposito $movimientoDeposito): bool
    {
        return $user->can('ver_contrato');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_contrato');
    }

    public function update(User $user, MovimientoDeposito $movimientoDeposito): bool
    {
        return $user->can('editar_contrato');
    }

    public function delete(User $user, MovimientoDeposito $movimientoDeposito): bool
    {
        return $user->can('eliminar_contrato');
    }
}
