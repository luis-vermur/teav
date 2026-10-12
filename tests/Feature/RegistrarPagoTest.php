<?php

namespace Tests\Feature;

use App\Filament\Pages\Inicio;
use App\Filament\Resources\Pagos\Pages\CreatePago;
use App\Filament\Resources\Pagos\PagoResource;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\User;
use App\Services\ContratoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Registrar un pago desde el módulo de Pagos.
 *
 * Había dos caminos para lo mismo y no se comportaban igual: desde el
 * contrato el pago se repartía solo, y desde aquí se guardaba sin acreditar.
 * El dinero entraba a la base y el contrato seguía reclamando la exhibición
 * como pendiente. Estas pruebas fijan que los dos terminen en PagoService.
 */
class RegistrarPagoTest extends TestCase
{
    use RefreshDatabase;

    protected function contratoVigente(): Contrato
    {
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);
        $this->seed(\Database\Seeders\DemoTeavSeeder::class);

        $u = User::first();
        $u->assignRole(Role::firstWhere('name', 'administrador'));

        Filament::setCurrentPanel('admin');
        $this->actingAs($u);

        $contrato = Contrato::firstWhere('estatus', 'borrador');
        $contrato->arrendatario->update(['estatus' => 'activo']);

        return app(ContratoService::class)->activar($contrato);
    }

    /**
     * El botón del tablero mandaba al listado de contratos: decía
     * "Registrar pago" y dejaba al usuario frente a una tabla.
     */
    public function test_el_boton_del_tablero_lleva_al_alta_de_pagos(): void
    {
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(Role::firstWhere('name', 'administrador'));
        Filament::setCurrentPanel('admin');
        $this->actingAs($u);

        $accion = Livewire::test(Inicio::class)
            ->instance()
            ->getHeaderActions();

        $registrar = collect($accion)->first(fn ($a) => $a->getName() === 'registrar_pago');

        $this->assertSame(PagoResource::getUrl('create'), $registrar->getUrl());
    }

    /** Un pago dado de alta desde Pagos se acredita solo, como desde el contrato. */
    public function test_el_pago_se_aplica_al_registrarse(): void
    {
        $contrato = $this->contratoVigente();

        $exhibicion = $contrato->pagosProgramados()->orderBy('fecha_vencimiento')->first();
        $this->assertSame('pendiente', $exhibicion->estatus);

        Livewire::test(CreatePago::class)
            ->fillForm([
                'contrato_id'     => $contrato->id,
                'arrendatario_id' => $contrato->arrendatario_id,
                'monto'           => (float) $exhibicion->monto_total,
                'fecha_pago'      => now(),
                'metodo_pago'     => 'transferencia',
                'estatus'         => 'registrado',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pago = Pago::latest('id')->first();

        $this->assertSame('aplicado', $pago->fresh()->estatus);
        $this->assertSame('pagado', $exhibicion->fresh()->estatus);
        $this->assertEqualsWithDelta(0, (float) $exhibicion->fresh()->saldo, 0.01);
    }

    /** Quién capturó el pago sale de la sesión, no se teclea. */
    public function test_guarda_quien_lo_registro(): void
    {
        $contrato = $this->contratoVigente();

        Livewire::test(CreatePago::class)
            ->fillForm([
                'contrato_id'     => $contrato->id,
                'arrendatario_id' => $contrato->arrendatario_id,
                'monto'           => 1000,
                'fecha_pago'      => now(),
                'metodo_pago'     => 'efectivo',
                'estatus'         => 'registrado',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(auth()->id(), Pago::latest('id')->first()->registrado_por);
    }

    /** Un pago rechazado no se acredita a ninguna exhibición. */
    public function test_un_pago_rechazado_no_se_aplica(): void
    {
        $contrato = $this->contratoVigente();
        $exhibicion = $contrato->pagosProgramados()->orderBy('fecha_vencimiento')->first();

        Livewire::test(CreatePago::class)
            ->fillForm([
                'contrato_id'     => $contrato->id,
                'arrendatario_id' => $contrato->arrendatario_id,
                'monto'           => (float) $exhibicion->monto_total,
                'fecha_pago'      => now(),
                'metodo_pago'     => 'cheque',
                'estatus'         => 'rechazado',
                'motivo_reverso'  => 'Cheque devuelto',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('rechazado', Pago::latest('id')->first()->estatus);
        $this->assertSame('pendiente', $exhibicion->fresh()->estatus);
    }
}
