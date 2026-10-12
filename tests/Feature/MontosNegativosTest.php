<?php

namespace Tests\Feature;

use App\Filament\Resources\Arrendatarios\Pages\CreateArrendatario;
use App\Filament\Resources\Unidads\Pages\CreateUnidad;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ningún campo de dinero acepta cantidades negativas.
 *
 * Un precio de adquisición en negativo no da un error visible: se guarda,
 * entra al cálculo de rentabilidad y devuelve rendimientos absurdos que
 * nadie relaciona con un dedazo de captura meses atrás. Es más barato
 * rechazarlo en el formulario.
 */
class MontosNegativosTest extends TestCase
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

    public function test_el_precio_de_un_vehiculo_no_puede_ser_negativo(): void
    {
        Livewire::test(CreateUnidad::class)
            ->fillForm(['precio_adquisicion' => -100000])
            ->call('create')
            ->assertHasFormErrors(['precio_adquisicion' => 'min']);
    }

    public function test_el_precio_de_lista_no_puede_ser_negativo(): void
    {
        Livewire::test(CreateUnidad::class)
            ->fillForm(['precio_lista' => -1])
            ->call('create')
            ->assertHasFormErrors(['precio_lista' => 'min']);
    }

    public function test_el_ingreso_declarado_de_un_cliente_no_puede_ser_negativo(): void
    {
        Livewire::test(CreateArrendatario::class)
            ->fillForm(['ingreso_mensual_declarado' => -50000])
            ->call('create')
            ->assertHasFormErrors(['ingreso_mensual_declarado' => 'min']);
    }

    /** El cero sí es válido: hay gastos y enganches que legítimamente son 0. */
    public function test_el_cero_sigue_siendo_valido(): void
    {
        Livewire::test(CreateUnidad::class)
            ->fillForm(['gastos_adquisicion' => 0])
            ->call('create')
            ->assertHasNoFormErrors(['gastos_adquisicion']);
    }
}
