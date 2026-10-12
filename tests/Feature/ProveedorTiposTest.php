<?php

namespace Tests\Feature;

use App\Filament\Resources\Proveedors\Pages\CreateProveedor;
use App\Models\Proveedor;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La columna `tipos` de proveedores es json y el modelo la castea a array.
 *
 * El formulario la capturaba con un TextInput, así que guardaba la cadena
 * "taller" donde el resto del sistema espera ["taller"]. El filtro seguía
 * funcionando de milagro (PostgreSQL es laxo comparando json), pero
 * cualquier in_array o foreach sobre el valor se rompía.
 *
 * Estas pruebas fijan las dos mitades del arreglo: que el formulario guarde
 * un arreglo, y que el modelo devuelva un arreglo aunque le llegue basura.
 */
class ProveedorTiposTest extends TestCase
{
    use RefreshDatabase;

    protected function usuarioConPermisos(): User
    {
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $u = User::factory()->create();
        $u->assignRole(Role::firstWhere('name', 'administrador'));

        Filament::setCurrentPanel('admin');

        return $u;
    }

    public function test_el_formulario_guarda_los_tipos_como_arreglo(): void
    {
        $this->actingAs($this->usuarioConPermisos());

        Livewire::test(CreateProveedor::class)
            ->fillForm([
                'nombre'   => 'Taller Hidalgo',
                'tipos'    => ['taller', 'llantera'],
                'telefono' => '442 111 2233',
                'activo'   => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Proveedor::firstWhere('nombre', 'Taller Hidalgo');

        $this->assertIsArray($p->tipos);
        $this->assertSame(['taller', 'llantera'], $p->tipos);
        // Y lo que llega a la base es json, no una cadena suelta.
        $this->assertJson($p->getRawOriginal('tipos'));
    }

    /** El RFC se normaliza para que no haya duplicados por capitalización. */
    public function test_el_rfc_se_guarda_en_mayusculas(): void
    {
        $this->actingAs($this->usuarioConPermisos());

        Livewire::test(CreateProveedor::class)
            ->fillForm([
                'nombre' => 'Gestoría del Centro',
                'rfc'    => ' xaxx010101000 ',
                'tipos'  => ['gestoria'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('XAXX010101000', Proveedor::firstWhere('nombre', 'Gestoría del Centro')->rfc);
    }

    /** Sin tipo no se puede dar de alta: quedaría fuera de todos los filtros. */
    public function test_exige_al_menos_un_tipo(): void
    {
        $this->actingAs($this->usuarioConPermisos());

        Livewire::test(CreateProveedor::class)
            ->fillForm(['nombre' => 'Sin clasificar', 'tipos' => []])
            ->call('create')
            ->assertHasFormErrors(['tipos']);
    }

    /**
     * Red de seguridad del modelo: un registro viejo, un seeder o una
     * importación pueden volver a meter una cadena suelta. El accesor la
     * envuelve en vez de dejar que reviente el primer foreach.
     */
    public function test_el_modelo_tolera_una_cadena_suelta(): void
    {
        $p = new Proveedor;
        $p->setRawAttributes(['tipos' => 'taller']);

        $this->assertSame(['taller'], $p->tipos);
    }

    public function test_el_modelo_devuelve_arreglo_vacio_si_no_hay_tipos(): void
    {
        $p = new Proveedor;
        $p->setRawAttributes(['tipos' => null]);

        $this->assertSame([], $p->tipos);
    }

    /** El filtro por tipo sigue encontrando lo que debe. */
    public function test_el_filtro_por_tipo_encuentra_el_proveedor(): void
    {
        Proveedor::create(['nombre' => 'Llantera Norte', 'tipos' => ['llantera'], 'activo' => true]);
        Proveedor::create(['nombre' => 'Agencia Sur', 'tipos' => ['agencia'], 'activo' => true]);

        $this->assertSame(1, Proveedor::deTipo('llantera')->count());
        $this->assertSame('Llantera Norte', Proveedor::deTipo('llantera')->first()->nombre);
    }
}
