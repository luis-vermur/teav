<?php

namespace Tests\Feature;

use App\Filament\Resources\Colors\Pages\CreateColor;
use App\Filament\Resources\Marcas\Pages\CreateMarca;
use App\Filament\Resources\Marcas\Pages\EditMarca;
use App\Filament\Resources\Submarcas\Pages\CreateSubmarca;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Submarca;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reglas de los catálogos de flota.
 *
 * Un catálogo existe para que nadie escriba la misma cosa de tres formas
 * distintas. Si deja pasar duplicados no sirve de nada: la flota acaba con
 * "Audi" y "audi" como marcas separadas y los reportes se parten en dos.
 */
class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $u = User::factory()->create();
        $u->assignRole(Role::firstWhere('name', 'administrador'));

        Filament::setCurrentPanel('admin');
        $this->actingAs($u);
    }

    public function test_no_deja_repetir_una_marca(): void
    {
        Marca::create(['nombre' => 'Audi', 'activo' => true]);

        Livewire::test(CreateMarca::class)
            ->fillForm(['nombre' => 'Audi'])
            ->call('create')
            ->assertHasFormErrors(['nombre']);
    }

    /** Editar una marca sin cambiarle el nombre no puede chocar consigo misma. */
    public function test_editar_una_marca_no_choca_con_ella_misma(): void
    {
        $m = Marca::create(['nombre' => 'Audi', 'activo' => true]);

        Livewire::test(EditMarca::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nombre' => 'Audi', 'pais_origen' => 'Alemania'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Alemania', $m->fresh()->pais_origen);
    }

    public function test_no_deja_repetir_un_color(): void
    {
        Color::create(['nombre' => 'Gris Daytona']);

        Livewire::test(CreateColor::class)
            ->fillForm(['nombre' => 'Gris Daytona'])
            ->call('create')
            ->assertHasFormErrors(['nombre']);
    }

    /**
     * El nombre de un modelo sólo es único dentro de su marca: puede haber
     * un "Ibiza" de Seat sin que eso impida otro de otra marca.
     */
    public function test_el_modelo_es_unico_dentro_de_su_marca(): void
    {
        $seat = Marca::create(['nombre' => 'Seat', 'activo' => true]);
        Submarca::create(['marca_id' => $seat->id, 'nombre' => 'Ibiza', 'activo' => true]);

        Livewire::test(CreateSubmarca::class)
            ->fillForm(['marca_id' => $seat->id, 'nombre' => 'Ibiza'])
            ->call('create')
            ->assertHasFormErrors(['nombre']);
    }

    public function test_el_mismo_modelo_si_puede_existir_en_otra_marca(): void
    {
        $seat = Marca::create(['nombre' => 'Seat', 'activo' => true]);
        $otra = Marca::create(['nombre' => 'Cupra', 'activo' => true]);
        Submarca::create(['marca_id' => $seat->id, 'nombre' => 'Ibiza', 'activo' => true]);

        Livewire::test(CreateSubmarca::class)
            ->fillForm(['marca_id' => $otra->id, 'nombre' => 'Ibiza'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Submarca::where('nombre', 'Ibiza')->count());
    }

    /** Las marcas se dan de alta activas: si no, no aparecen en ningún select. */
    public function test_una_marca_nueva_nace_activa(): void
    {
        Livewire::test(CreateMarca::class)
            ->fillForm(['nombre' => 'Cupra'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Marca::firstWhere('nombre', 'Cupra')->activo);
    }
}
