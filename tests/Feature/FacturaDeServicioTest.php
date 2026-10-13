<?php

namespace Tests\Feature;

use App\Filament\Resources\Mantenimientos\RelationManagers\DocumentosRelationManager;
use App\Models\{Color, Documento, Mantenimiento, Marca, Submarca, TipoDocumento, TipoMantenimiento, Unidad, User};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Expediente de un servicio: la factura del taller y la orden de trabajo.
 *
 * La factura no es decorativa. Cuando el servicio se marca como recobrable,
 * su costo se le carga al cliente, y es el papel que respalda ese cargo si
 * lo reclama meses después.
 */
class FacturaDeServicioTest extends TestCase
{
    use RefreshDatabase;

    protected Mantenimiento $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);
        $this->seed(\Database\Seeders\TiposDocumentoSeeder::class);
        $this->seed(\Database\Seeders\TiposMantenimientoSeeder::class);

        Filament::setCurrentPanel('admin');

        $marca    = Marca::create(['nombre' => 'Audi', 'activo' => true]);
        $submarca = Submarca::create(['marca_id' => $marca->id, 'nombre' => 'Q3', 'activo' => true]);

        $unidad = Unidad::create([
            'codigo_interno' => 'TEAV-001', 'vin' => 'WAUBYAF35S1026242',
            'submarca_id' => $submarca->id, 'anio' => 2025, 'precio_adquisicion' => 815000,
        ]);

        $this->servicio = Mantenimiento::create([
            'unidad_id' => $unidad->id,
            'tipo_mantenimiento_id' => TipoMantenimiento::first()->id,
            'fecha' => now(), 'kilometraje' => 15000,
            'costo_refacciones' => 1200, 'costo_mano_obra' => 800,
            'estatus' => 'completado', 'responsable_costo' => 'arrendatario',
        ]);
    }

    protected function comoRol(string $rol): User
    {
        $u = User::factory()->create();
        $u->syncRoles([$rol]);
        $this->actingAs($u);

        return $u;
    }

    public function test_el_selector_ofrece_los_tipos_del_servicio(): void
    {
        $tipos = TipoDocumento::where('aplica_a', Mantenimiento::class)->pluck('clave')->all();

        $this->assertContains('factura_servicio', $tipos);
        $this->assertContains('orden_trabajo', $tipos);
    }

    public function test_la_seccion_aparece_en_la_ficha_del_servicio(): void
    {
        $this->comoRol('administrador');

        Livewire::test(DocumentosRelationManager::class, [
            'ownerRecord' => $this->servicio,
            'pageClass'   => \App\Filament\Resources\Mantenimientos\Pages\ViewMantenimiento::class,
        ])->assertOk();
    }

    /** El camino completo: subir la factura y que quede en el disco privado. */
    public function test_sube_la_factura_al_disco_privado(): void
    {
        Storage::fake('documentos');
        $usuario = $this->comoRol('administrador');

        $tipo = TipoDocumento::firstWhere('clave', 'factura_servicio');

        Livewire::test(DocumentosRelationManager::class, [
            'ownerRecord' => $this->servicio,
            'pageClass'   => \App\Filament\Resources\Mantenimientos\Pages\ViewMantenimiento::class,
        ])
            ->callTableAction('create', data: [
                'tipo_documento_id' => $tipo->id,
                'ruta' => UploadedFile::fake()->create('factura-taller.pdf', 120, 'application/pdf'),
            ])
            ->assertHasNoActionErrors();

        $doc = Documento::first();

        $this->assertNotNull($doc, 'No se registró el documento.');
        $this->assertSame(Mantenimiento::class, $doc->documentable_type);
        $this->assertSame($this->servicio->id, $doc->documentable_id);
        $this->assertStringStartsWith('mantenimientos/', $doc->ruta);
        $this->assertSame($usuario->id, $doc->subido_por);
        $this->assertNotNull($doc->hash, 'Sin hash no se detectan duplicados.');

        Storage::disk('documentos')->assertExists($doc->ruta);
    }

    /** Se pueden subir varias facturas: un servicio puede traer más de una. */
    public function test_el_tipo_factura_admite_varios_archivos(): void
    {
        $this->assertTrue(
            (bool) TipoDocumento::firstWhere('clave', 'factura_servicio')->permite_multiples,
        );
    }

    /** Quien sólo consulta no puede subir papeles. */
    public function test_consulta_no_puede_subir(): void
    {
        $this->comoRol('consulta');

        $this->assertFalse(\Illuminate\Support\Facades\Gate::allows('create', Documento::class));
    }
}
