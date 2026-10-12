<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Recursos del sistema. Cada uno genera 4 permisos.
        $recursos = [
            'unidad', 'mantenimiento', 'poliza', 'siniestro',
            'arrendatario', 'contrato', 'cotizacion',
            'pago', 'penalizacion', 'documento',
            'catalogo', 'reporte', 'usuario',
        ];

        foreach ($recursos as $recurso) {
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $accion) {
                Permission::firstOrCreate(['name' => "{$accion}_{$recurso}"]);
            }
        }

        // Permisos especiales: acciones sensibles que no son CRUD
        foreach ([
            'activar_contrato',
            'reversar_pago',
            'condonar_adeudo',
            'convertir_cotizacion',
            'ver_rentabilidad',
            'cerrar_contrato',
        ] as $especial) {
            Permission::firstOrCreate(['name' => $especial]);
        }

        // ---------- Roles ----------

        // Ve todo, hace todo
        $admin = Role::firstOrCreate(['name' => 'administrador']);
        $admin->syncPermissions(Permission::all());

        // Opera el día a día, pero no borra ni condona
        $gerente = Role::firstOrCreate(['name' => 'gerente']);
        $gerente->syncPermissions(
            Permission::where('name', 'not like', 'eliminar_%')
                ->where('name', '!=', 'ver_usuario')
                ->where('name', '!=', 'crear_usuario')
                ->where('name', '!=', 'editar_usuario')
                ->get()
        );

        // Captura pagos y consulta; no toca contratos ni unidades
        $cobranza = Role::firstOrCreate(['name' => 'cobranza']);
        $cobranza->syncPermissions([
            'ver_contrato', 'ver_arrendatario', 'ver_unidad',
            'ver_pago', 'crear_pago', 'editar_pago',
            'ver_penalizacion', 'crear_penalizacion',
            'ver_documento', 'crear_documento',
        ]);

        // Administra la flota, no ve dinero
        $flotilla = Role::firstOrCreate(['name' => 'flotilla']);
        $flotilla->syncPermissions([
            'ver_unidad', 'crear_unidad', 'editar_unidad',
            'ver_mantenimiento', 'crear_mantenimiento', 'editar_mantenimiento',
            'ver_poliza', 'crear_poliza', 'editar_poliza',
            'ver_siniestro', 'crear_siniestro', 'editar_siniestro',
            'ver_documento', 'crear_documento',
            'ver_catalogo', 'crear_catalogo', 'editar_catalogo',
        ]);

        /**
         * OPERADOR: la gente que usa el sistema todos los días.
         *
         * Hace toda la operación (clientes, vehículos, contratos, pagos)
         * pero NO administra la configuración del sistema.
         *
         * La distinción importante está en los catálogos:
         *   crear_catalogo  SÍ  -> puede dar de alta una marca al vuelo
         *                          desde el + del select de vehículo.
         *   ver_catalogo    NO  -> pero Marcas/Modelos/Colores/Aseguradoras
         *                          no le aparecen en el menú, porque el
         *                          viewAny() de sus policies devuelve false.
         *
         * Esconder del menú no es lo mismo que quitar el permiso.
         *
         * Lo que se le niega a propósito y por qué:
         *   reversar_pago, condonar_adeudo -> borran dinero, van en gerencia
         *   cerrar_contrato                -> liquida el depósito en garantía
         *   ver_rentabilidad               -> información sensible del negocio
         *   eliminar_*                     -> nadie de operación borra
         *   ver_usuario                    -> no administra cuentas
         */
        $operador = Role::firstOrCreate(['name' => 'operador']);
        $operador->syncPermissions([
            // Clientes. Ojo: el recurso se llama "arrendatario", no "cliente";
            // "Clientes" es sólo la etiqueta que ve el usuario en el menú.
            'ver_arrendatario', 'crear_arrendatario', 'editar_arrendatario',

            'ver_unidad', 'crear_unidad', 'editar_unidad',

            'ver_contrato', 'crear_contrato', 'editar_contrato', 'activar_contrato',

            'ver_cotizacion', 'crear_cotizacion', 'editar_cotizacion', 'convertir_cotizacion',

            // Registra pagos, pero no los reversa.
            'ver_pago', 'crear_pago',

            // Consulta recargos; condonarlos es decisión de gerencia.
            'ver_penalizacion',

            'ver_mantenimiento', 'crear_mantenimiento', 'editar_mantenimiento',
            'ver_poliza', 'crear_poliza', 'editar_poliza',
            'ver_siniestro', 'crear_siniestro', 'editar_siniestro',
            'ver_documento', 'crear_documento',

            // Alta al vuelo de catálogos, sin acceso a administrarlos.
            'crear_catalogo',
        ]);

        // Sólo lectura, para dirección o auditoría externa
        $consulta = Role::firstOrCreate(['name' => 'consulta']);
        $consulta->syncPermissions(
            Permission::where('name', 'like', 'ver_%')->get()
        );

        $this->command->info('Roles creados: administrador, gerente, operador, cobranza, flotilla, consulta');

        /**
         * Usuario de prueba del rol operador.
         *
         * SÓLO fuera de producción. Es una cuenta con correo y contraseña
         * conocidos: en un panel publicado en internet equivale a dejar la
         * puerta abierta. Y como los roles y permisos sí conviene resembrarlos
         * en cada despliegue —son configuración, no datos—, este seeder acaba
         * corriendo en producción tarde o temprano.
         */
        if (app()->environment('production')) {
            $this->command->warn('Entorno de producción: no se creó el usuario de prueba.');

            return;
        }

        $operadorUser = User::firstOrCreate(
            ['email' => 'operador@teav.mx'],
            [
                'name'     => 'Operador de prueba',
                'password' => Hash::make('operador123'),
            ],
        );

        if (! $operadorUser->hasRole('operador')) {
            $operadorUser->assignRole('operador');
        }

        $this->command->info('Usuario de prueba: operador@teav.mx / operador123 (cambiar la contraseña)');
    }
}