<?php

namespace App\Policies;

use App\Models\Marca;
use App\Models\User;

/**
 * Catálogo: Marca.
 *
 * viewAny() gobierna si el recurso aparece en el menú de navegación.
 * create() gobierna el alta, incluida la creación al vuelo desde el +
 * de un createOptionForm.
 *
 * Van separados a propósito: el rol operador tiene crear_catalogo pero
 * NO ver_catalogo, así que no ve "Marcas" en el menú y aun así puede
 * darla de alta sin salir del formulario de vehículo.
 */
class MarcaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver_catalogo');
    }

    public function view(User $user, Marca $marca): bool
    {
        return $user->can('ver_catalogo');
    }

    public function create(User $user): bool
    {
        return $user->can('crear_catalogo');
    }

    public function update(User $user, Marca $marca): bool
    {
        return $user->can('editar_catalogo');
    }

    public function delete(User $user, Marca $marca): bool
    {
        return $user->can('eliminar_catalogo');
    }
}
