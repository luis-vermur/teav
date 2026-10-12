<?php

namespace App\Policies;

use App\Models\Submarca;
use App\Models\User;

/**
 * Catálogo: Submarca.
 *
 * viewAny() gobierna si el recurso aparece en el menú de navegación.
 * create() gobierna el alta, incluida la creación al vuelo desde el +
 * de un createOptionForm.
 *
 * Van separados a propósito: el rol operador tiene crear_catalogo pero
 * NO ver_catalogo, así que no ve "Submarcas" en el menú y aun así puede
 * darla de alta sin salir del formulario de vehículo.
 */
class SubmarcaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_catalogo');
    }

    public function view(User $user, Submarca $submarca): bool
    {
        return $user->can('ver_catalogo');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_catalogo');
    }

    public function update(User $user, Submarca $submarca): bool
    {
        return $user->can('editar_catalogo');
    }

    public function delete(User $user, Submarca $submarca): bool
    {
        return $user->can('eliminar_catalogo');
    }
}
