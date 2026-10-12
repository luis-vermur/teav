<?php

namespace Database\Seeders;

use App\Models\Arrendatario;
use App\Models\Aseguradora;
use App\Models\Color;
use App\Models\Contrato;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\Submarca;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración tomados del Excel real de TEAV.
 * Unidad: Audi Q3 2025 gris.
 *
 * Deja el contrato en estatus 'borrador' a propósito: activarlo
 * es un paso manual para poder ver funcionar las validaciones.
 */
class DemoTeavSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            $this->command->error('No hay usuarios. Corre primero: php artisan make:filament-user');
            return;
        }

        // ---------- Catálogos ----------

        $audi = Marca::firstOrCreate(
            ['nombre' => 'Audi'],
            ['pais_origen' => 'Alemania']
        );

        $q3 = Submarca::firstOrCreate(
            ['marca_id' => $audi->id, 'nombre' => 'Q3'],
            ['segmento' => 'SUV']
        );

        $gris = Color::firstOrCreate(['nombre' => 'Gris'], ['hex' => '#808080']);

        $agencia = Proveedor::firstOrCreate(
            ['nombre' => 'Audi Center Querétaro'],
            ['tipos' => ['agencia'], 'telefono' => '442 000 0000']
        );

        $aseguradora = Aseguradora::firstOrCreate(
            ['nombre' => 'GNP Seguros'],
            ['telefono_siniestros' => '800 400 9000']
        );

        // ---------- Unidad ----------
        // Cifras exactas del Excel: precio base 899,000 / descuento 736,500

        $unidad = Unidad::firstOrCreate(
            ['vin' => 'WAUZZZ8V5KA123456'],
            [
                'codigo_interno'          => 'TEAV-001',
                'placas'                  => 'UAB-123-C',
                'submarca_id'             => $q3->id,
                'color_id'                => $gris->id,
                'anio'                    => 2025,
                'version'                 => 'Advanced 35 TFSI',
                'transmision'             => 'automatica',
                'combustible'             => 'gasolina',
                'numero_pasajeros'        => 5,
                'fecha_adquisicion'       => now()->subMonths(2),
                'proveedor_id'            => $agencia->id,
                'factura_folio'           => 'A-45821',
                'precio_lista'            => 899000.00,
                'precio_adquisicion'      => 736500.00,
                'gastos_adquisicion'      => 18500.00,   // placas, gestoría, traslado
                'forma_adquisicion'       => 'contado',
                'valor_residual_estimado' => 449500.00,  // "venta unidad" del Excel
                'kilometraje_inicial'     => 15,
                'kilometraje_actual'      => 1240,
                'estatus'                 => 'disponible',
                'ubicacion'               => 'Patio Querétaro',
                'registrado_por'          => $user->id,
            ]
        );

        // ---------- Póliza (requisito para activar) ----------

        $unidad->polizas()->firstOrCreate(
            ['numero_poliza' => 'POL-2026-88123'],
            [
                'aseguradora_id'  => $aseguradora->id,
                'tipo_cobertura'  => 'amplia',
                'vigencia_inicio' => now()->subMonths(2),
                'vigencia_fin'    => now()->addMonths(10),
                'prima_total'     => 28400.00,
                'suma_asegurada'  => 899000.00,
                'deducible_danos' => 0.05,
                'deducible_robo'  => 0.10,
                'forma_pago'      => 'contado',
                'estatus'         => 'vigente',
            ]
        );

        // ---------- Arrendatario ----------

        $arrendatario = Arrendatario::firstOrCreate(
            ['rfc' => 'MASJ850312H45'],
            [
                'tipo_persona'              => 'fisica',
                'nombre'                    => 'Jorge',
                'apellido_paterno'          => 'Martínez',
                'apellido_materno'          => 'Solís',
                'curp'                      => 'MASJ850312HQTRLR07',
                'fecha_nacimiento'          => '1985-03-12',
                'nacionalidad'              => 'Mexicana',
                'ocupacion'                 => 'Consultor independiente',
                'email'                     => 'jorge.martinez@ejemplo.mx',
                'telefono'                  => '442 111 2233',
                'regimen_fiscal'            => '612',
                'estatus'                   => 'activo',
                'calificacion_interna'      => 4,
                'ingreso_mensual_declarado' => 85000.00,
                'registrado_por'            => $user->id,
            ]
        );

        $arrendatario->direcciones()->firstOrCreate(
            ['tipo' => 'fiscal'],
            [
                'calle'           => 'Av. Constituyentes',
                'numero_exterior' => '150',
                'colonia'         => 'Centro',
                'municipio'       => 'Querétaro',
                'estado'          => 'Querétaro',
                'codigo_postal'   => '76000',
                'es_principal'    => true,
            ]
        );

        $arrendatario->referencias()->firstOrCreate(
            ['nombre' => 'Ana Ruiz Vega'],
            [
                'tipo'       => 'personal',
                'relacion'   => 'Amiga',
                'telefono'   => '442 555 6677',
                'verificada' => true,
                'fecha_verificacion' => now(),
            ]
        );

        // ---------- Contrato en borrador ----------
        // Renta con IVA 24,360 y plazo 48 meses, igual que el Excel.
        // Sin IVA: 24,360 / 1.16 = 21,000 exactos.

        $contrato = Contrato::firstOrCreate(
            ['unidad_id' => $unidad->id, 'arrendatario_id' => $arrendatario->id],
            [
                'fecha_firma'            => now(),
                'fecha_inicio'           => now()->startOfMonth()->addMonth(),
                'fecha_fin'              => now()->startOfMonth()->addMonths(49),
                'plazo_meses'            => 48,
                'renta_sin_iva'          => 21000.00,
                'tasa_iva'               => 0.16,
                'renta_con_iva'          => 24360.00,
                'dia_corte'              => 5,
                'periodicidad'           => 'mensual',
                'deposito_garantia'      => 24360.00,
                'enganche'               => 0,
                'dias_gracia'            => 5,
                'tipo_penalizacion'      => 'porcentaje_mensual',
                'valor_penalizacion'     => 0.05,      // 5% mensual sobre saldo vencido
                'kilometraje_inicial'    => 1240,
                'limite_km_anual'        => 20000,
                'costo_km_excedente'     => 3.50,
                'valor_residual_pactado' => 449500.00,
                'con_opcion_compra'      => true,
                'estatus'                => 'borrador',
                'registrado_por'         => $user->id,
            ]
        );

        $this->command->info("Unidad:       {$unidad->codigo_interno} — {$unidad->descripcion}");
        $this->command->info("Arrendatario: {$arrendatario->codigo} — {$arrendatario->nombre_completo}");
        $this->command->info("Contrato:     {$contrato->folio} — estatus {$contrato->estatus}");
        $this->command->newLine();
        $this->command->warn('El contrato quedó en BORRADOR. Actívalo desde tinker para ver las validaciones.');
    }
}
