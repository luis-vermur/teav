<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Asigna un rol a un usuario desde la consola.
 *
 * Existe para el arranque y para el día a día en producción: un usuario sin
 * rol no puede entrar al panel (ver `User::canAccessPanel`), y el primero de
 * todos no tiene a nadie que se lo asigne desde la interfaz. Escribir eso con
 * `tinker --execute` dentro de un contenedor es un ejercicio de comillas.
 *
 *   php artisan teav:rol                          ← lista usuarios y roles
 *   php artisan teav:rol luis@teav.mx administrador
 */
class AsignarRol extends Command
{
    protected $signature = 'teav:rol
        {email? : Correo del usuario}
        {rol? : Nombre del rol}
        {--reemplazar : Deja sólo este rol, quitando los demás}';

    protected $description = 'Asigna un rol a un usuario (sin argumentos, lista lo que hay)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $rol   = $this->argument('rol');

        // Sin argumentos: enseñar el panorama en vez de pedir adivinanzas.
        if (! $email || ! $rol) {
            return $this->mostrarPanorama();
        }

        $usuario = User::where('email', $email)->first();

        if (! $usuario) {
            $this->error("No hay ningún usuario con el correo {$email}.");
            $this->mostrarPanorama();

            return self::FAILURE;
        }

        if (! Role::where('name', $rol)->exists()) {
            $this->error("El rol «{$rol}» no existe.");
            $this->line('  Disponibles: ' . Role::pluck('name')->implode(', '));

            return self::FAILURE;
        }

        $this->option('reemplazar')
            ? $usuario->syncRoles([$rol])
            : $usuario->assignRole($rol);

        $usuario->refresh();

        $this->components->info("{$usuario->name} ahora es: " . $usuario->getRoleNames()->implode(', '));
        $this->line('  Permisos efectivos: ' . $usuario->getAllPermissions()->count());

        return self::SUCCESS;
    }

    protected function mostrarPanorama(): int
    {
        $usuarios = User::with('roles')->orderBy('id')->get();

        if ($usuarios->isEmpty()) {
            $this->warn('No hay usuarios. Crea uno con `php artisan make:filament-user`.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>Usuario</>', '<options=bold>Rol</>');

        foreach ($usuarios as $u) {
            $roles = $u->getRoleNames();

            $this->components->twoColumnDetail(
                "{$u->name}  <fg=gray>{$u->email}</>",
                $roles->isEmpty()
                    ? '<fg=red>sin rol — no puede entrar al panel</>'
                    : "<fg=green>{$roles->implode(', ')}</>",
            );
        }

        $this->newLine();
        $this->line('  Roles disponibles: ' . Role::pluck('name')->implode(', '));
        $this->line('  Para asignar: <fg=cyan>php artisan teav:rol correo@ejemplo.mx administrador</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
