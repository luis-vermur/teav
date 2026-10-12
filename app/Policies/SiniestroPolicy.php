<?php

namespace App\Policies;

use App\Models\Siniestro;
use App\Models\User;

/**
 * Siniestros reportados.
 *
 * IMPORTANTE: sin una policy, Gate::allows() devuelve FALSE. Los relation
 * managers consultan el Gate directamente, así que un modelo sin policy
 * deja sus botones deshabilitados sin explicación. (Los Resources de
 * Filament sí tienen respaldo de "sin policy, permite", que es el problema
 * contrario: quedan abiertos a cualquiera.)
 */
class SiniestroPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_siniestro');
    }

    public function view(User $user, Siniestro $siniestro): bool
    {
        return $user->can('ver_siniestro');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_siniestro');
    }

    public function update(User $user, Siniestro $siniestro): bool
    {
        return $user->can('editar_siniestro');
    }

    public function delete(User $user, Siniestro $siniestro): bool
    {
        return $user->can('eliminar_siniestro');
    }
}
