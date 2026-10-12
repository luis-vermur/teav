<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quién puede entrar al panel.
 *
 * Filament responde 403 a todo el mundo en producción si el modelo User no
 * implementa FilamentUser. Es una red de seguridad suya, y en local pasa
 * desapercibida: el panel funciona en la máquina del desarrollador y se
 * cierra en cuanto se despliega.
 *
 * Estas pruebas fijan la regla para que no vuelva a perderse.
 */
class AccesoAlPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_el_modelo_user_implementa_el_contrato_de_filament(): void
    {
        $this->assertInstanceOf(
            \Filament\Models\Contracts\FilamentUser::class,
            new User,
            'Sin esto, Filament responde 403 a todos en producción.',
        );
    }

    public function test_un_usuario_con_rol_entra(): void
    {
        $u = User::factory()->create();
        $u->assignRole(Role::firstWhere('name', 'administrador'));

        $this->assertTrue($u->canAccessPanel(Filament::getPanel('admin')));
    }

    /**
     * Un usuario sin rol no tiene ni un permiso: entraría a un panel sin un
     * solo módulo visible y parecería que el sistema está roto.
     */
    public function test_un_usuario_sin_rol_no_entra(): void
    {
        $u = User::factory()->create();
        $u->syncRoles([]);

        $this->assertFalse($u->canAccessPanel(Filament::getPanel('admin')));
        $this->assertSame(0, $u->getAllPermissions()->count());
    }

    /** Quitarle el rol a alguien le cierra la puerta de inmediato. */
    public function test_quitar_el_rol_revoca_el_acceso(): void
    {
        $u = User::factory()->create();
        $u->assignRole('operador');

        $this->assertTrue($u->canAccessPanel(Filament::getPanel('admin')));

        $u->syncRoles([]);

        $this->assertFalse($u->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    /** El comando de consola es la única vía para el primer usuario. */
    public function test_el_comando_asigna_el_rol(): void
    {
        $u = User::factory()->create(['email' => 'jefe@teav.mx']);
        $u->syncRoles([]);

        $this->artisan('teav:rol jefe@teav.mx administrador')->assertSuccessful();

        $this->assertTrue($u->fresh()->hasRole('administrador'));
    }

    public function test_el_comando_rechaza_un_rol_inexistente(): void
    {
        $u = User::factory()->create(['email' => 'jefe@teav.mx']);

        $this->artisan('teav:rol jefe@teav.mx inventado')->assertFailed();
    }
}
