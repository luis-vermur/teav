<?php

namespace Database\Seeders;

use App\Models\Marca;
use App\Models\Submarca;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CATÁLOGO DE MARCAS Y SUBMARCAS (MODELOS)
 *
 * De dónde salen los datos:
 * Marcas con presencia comercial en México y sus modelos vigentes en el
 * mercado mexicano al momento de escribir este seeder. La selección está
 * sesgada a propósito hacia lo que una arrendadora realmente compra:
 * sedanes compactos, SUVs y pickups de trabajo. No es un catálogo exhaustivo
 * del portafolio de cada armadora: se omiten deportivos, ediciones especiales
 * y versiones de bajo volumen que una flotilla no adquiere.
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ ADVERTENCIA: ESTE CATÁLOGO ENVEJECE.                                 │
 * │ Los modelos entran y salen del mercado cada año. Las marcas chinas    │
 * │ en particular renuevan su alineación muy rápido y varias llevan menos │
 * │ de un par de años en México. REVISAR ANUALMENTE contra las listas de  │
 * │ precios oficiales de cada armadora.                                   │
 * │                                                                       │
 * │ Este seeder es aditivo: volver a correrlo agrega los modelos nuevos   │
 * │ sin tocar ni borrar lo ya capturado. Para dar de baja un modelo       │
 * │ descontinuado NO lo quites de aquí: marca `activo = false` en la UI,  │
 * │ porque puede haber unidades y contratos históricos apuntando a él.    │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * Segmentos válidos: Sedán, SUV, Hatchback, Pickup, Van, Deportivo, Coupé.
 *
 * Estructura: marca => ['pais' => ..., 'modelos' => [[modelo, segmento], ...]]
 */
class MarcasSeeder extends Seeder
{
    /**
     * Segmentos permitidos. Se valida contra esta lista para que un typo
     * ("SUv", "Pick-up") no se cuele silenciosamente al catálogo.
     */
    private const SEGMENTOS = [
        'Sedán', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Deportivo', 'Coupé',
    ];

    public function run(): void
    {
        $marcas = $this->marcas();

        $marcasCreadas = 0;
        $marcasExistentes = 0;
        $modelosCreados = 0;
        $modelosExistentes = 0;

        $this->validarSegmentos($marcas);

        // Se agrupa por marca: una transacción por marca, con todos sus
        // modelos dentro. Así los writes van en lote pero cada fila sigue
        // pasando por firstOrCreate, que es lo que garantiza la idempotencia.
        foreach ($marcas as $nombreMarca => $datos) {
            DB::transaction(function () use (
                $nombreMarca, $datos,
                &$marcasCreadas, &$marcasExistentes, &$modelosCreados, &$modelosExistentes
            ) {
                $marca = Marca::firstOrCreate(
                    ['nombre' => $nombreMarca],              // único de `nombre`
                    ['pais_origen' => $datos['pais'], 'activo' => true],
                );

                $marca->wasRecentlyCreated ? $marcasCreadas++ : $marcasExistentes++;

                foreach ($datos['modelos'] as [$modelo, $segmento]) {
                    // La búsqueda va por ['marca_id', 'nombre'] para respetar
                    // el índice único compuesto de la tabla submarcas.
                    $submarca = Submarca::firstOrCreate(
                        ['marca_id' => $marca->id, 'nombre' => $modelo],
                        ['segmento' => $segmento, 'activo' => true],
                    );

                    $submarca->wasRecentlyCreated ? $modelosCreados++ : $modelosExistentes++;
                }
            });
        }

        $this->command->info("Marcas: {$marcasCreadas} creadas, {$marcasExistentes} ya existían.");
        $this->command->info("Modelos: {$modelosCreados} creados, {$modelosExistentes} ya existían.");
    }

    /**
     * Falla temprano si algún segmento no está en la lista permitida.
     */
    private function validarSegmentos(array $marcas): void
    {
        foreach ($marcas as $nombreMarca => $datos) {
            foreach ($datos['modelos'] as [$modelo, $segmento]) {
                if (! in_array($segmento, self::SEGMENTOS, true)) {
                    throw new \InvalidArgumentException(
                        "Segmento inválido '{$segmento}' en {$nombreMarca} {$modelo}. "
                        . 'Válidos: ' . implode(', ', self::SEGMENTOS)
                    );
                }
            }
        }
    }

    /**
     * @return array<string, array{pais: string, modelos: array<array{0: string, 1: string}>}>
     */
    private function marcas(): array
    {
        return [
            // ================================================================
            // VOLUMEN / FLOTILLA
            // El pan de cada día de una arrendadora: alto volumen, refacciones
            // baratas, red de servicio amplia y buen valor de reventa.
            // ================================================================

            'Nissan' => ['pais' => 'Japón', 'modelos' => [
                ['Versa', 'Sedán'],
                ['Sentra', 'Sedán'],
                ['Altima', 'Sedán'],
                ['V-Drive', 'Sedán'],
                ['March', 'Hatchback'],
                ['Kicks', 'SUV'],
                ['Qashqai', 'SUV'],
                ['X-Trail', 'SUV'],
                ['Pathfinder', 'SUV'],
                ['Frontier', 'Pickup'],
                ['NP300', 'Pickup'],
                ['Urvan', 'Van'],
            ]],

            'Volkswagen' => ['pais' => 'Alemania', 'modelos' => [
                ['Jetta', 'Sedán'],
                ['Virtus', 'Sedán'],
                ['Vento', 'Sedán'],
                ['Polo', 'Hatchback'],
                ['Golf', 'Hatchback'],
                ['T-Cross', 'SUV'],
                ['Nivus', 'SUV'],
                ['Taos', 'SUV'],
                ['Tiguan', 'SUV'],
                ['Teramont', 'SUV'],
                ['Saveiro', 'Pickup'],
                ['Amarok', 'Pickup'],
                ['Caddy', 'Van'],
                ['Transporter', 'Van'],
            ]],

            'Toyota' => ['pais' => 'Japón', 'modelos' => [
                ['Yaris', 'Sedán'],
                ['Corolla', 'Sedán'],
                ['Camry', 'Sedán'],
                ['Prius', 'Sedán'],
                ['Raize', 'SUV'],
                ['Corolla Cross', 'SUV'],
                ['RAV4', 'SUV'],
                ['Highlander', 'SUV'],
                ['Hilux', 'Pickup'],
                ['Tacoma', 'Pickup'],
                ['Tundra', 'Pickup'],
                ['Hiace', 'Van'],
                ['Sienna', 'Van'],
            ]],

            'Chevrolet' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['Aveo', 'Sedán'],
                ['Onix', 'Sedán'],
                ['Cavalier', 'Sedán'],
                ['Groove', 'SUV'],
                ['Tracker', 'SUV'],
                ['Captiva', 'SUV'],
                ['Equinox', 'SUV'],
                ['Blazer', 'SUV'],
                ['Traverse', 'SUV'],
                ['Tornado', 'Pickup'],
                ['S10', 'Pickup'],
                ['Colorado', 'Pickup'],
                ['Silverado', 'Pickup'],
                ['Express', 'Van'],
            ]],

            'Kia' => ['pais' => 'Corea del Sur', 'modelos' => [
                ['Rio', 'Sedán'],
                ['K3', 'Sedán'],
                ['Forte', 'Sedán'],
                ['K3 Cross', 'Hatchback'],
                ['Soul', 'SUV'],
                ['Seltos', 'SUV'],
                ['Sportage', 'SUV'],
                ['Sorento', 'SUV'],
                ['Niro', 'SUV'],
                ['Carnival', 'Van'],
            ]],

            'Hyundai' => ['pais' => 'Corea del Sur', 'modelos' => [
                ['Accent', 'Sedán'],
                ['Elantra', 'Sedán'],
                ['Grand i10', 'Hatchback'],
                ['Venue', 'SUV'],
                ['Creta', 'SUV'],
                ['Kona', 'SUV'],
                ['Tucson', 'SUV'],
                ['Santa Fe', 'SUV'],
                ['Palisade', 'SUV'],
                ['Ioniq 5', 'SUV'],
            ]],

            'Mazda' => ['pais' => 'Japón', 'modelos' => [
                ['Mazda 2 Sedán', 'Sedán'],
                ['Mazda 3 Sedán', 'Sedán'],
                ['Mazda 2', 'Hatchback'],
                ['Mazda 3', 'Hatchback'],
                ['CX-3', 'SUV'],
                ['CX-30', 'SUV'],
                ['CX-5', 'SUV'],
                ['CX-50', 'SUV'],
                ['CX-70', 'SUV'],
                ['CX-90', 'SUV'],
                ['BT-50', 'Pickup'],
            ]],

            'Honda' => ['pais' => 'Japón', 'modelos' => [
                ['City', 'Sedán'],
                ['Civic', 'Sedán'],
                ['Accord', 'Sedán'],
                ['HR-V', 'SUV'],
                ['BR-V', 'SUV'],
                ['ZR-V', 'SUV'],
                ['CR-V', 'SUV'],
                ['Pilot', 'SUV'],
                ['Odyssey', 'Van'],
            ]],

            'Ford' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['Escape', 'SUV'],
                ['Territory', 'SUV'],
                ['Edge', 'SUV'],
                ['Explorer', 'SUV'],
                ['Expedition', 'SUV'],
                ['Bronco', 'SUV'],
                ['Bronco Sport', 'SUV'],
                ['Maverick', 'Pickup'],
                ['Ranger', 'Pickup'],
                ['F-150', 'Pickup'],
                ['Lobo', 'Pickup'],
                ['Transit', 'Van'],
                ['Mustang', 'Deportivo'],
            ]],

            'Renault' => ['pais' => 'Francia', 'modelos' => [
                ['Logan', 'Sedán'],
                ['Kwid', 'Hatchback'],
                ['Stepway', 'Hatchback'],
                ['Kardian', 'SUV'],
                ['Duster', 'SUV'],
                ['Captur', 'SUV'],
                ['Koleos', 'SUV'],
                ['Oroch', 'Pickup'],
                ['Kangoo', 'Van'],
            ]],

            'Suzuki' => ['pais' => 'Japón', 'modelos' => [
                ['Ciaz', 'Sedán'],
                ['Swift', 'Hatchback'],
                ['Ignis', 'Hatchback'],
                ['Fronx', 'SUV'],
                ['Vitara', 'SUV'],
                ['Grand Vitara', 'SUV'],
                ['S-Cross', 'SUV'],
                ['Jimny', 'SUV'],
                ['Ertiga', 'Van'],
            ]],

            'SEAT' => ['pais' => 'España', 'modelos' => [
                ['Ibiza', 'Hatchback'],
                ['León', 'Hatchback'],
                ['Arona', 'SUV'],
                ['Ateca', 'SUV'],
                ['Tarraco', 'SUV'],
            ]],

            // ================================================================
            // PREMIUM
            // Volumen bajo pero renta alta. Ojo con el costo de mantenimiento
            // y refacciones al calcular la rentabilidad del arrendamiento.
            // ================================================================

            'Audi' => ['pais' => 'Alemania', 'modelos' => [
                ['A3', 'Sedán'],
                ['A4', 'Sedán'],
                ['A6', 'Sedán'],
                ['A5', 'Coupé'],
                ['Q2', 'SUV'],
                ['Q3', 'SUV'],
                ['Q5', 'SUV'],
                ['Q7', 'SUV'],
                ['Q8', 'SUV'],
                ['e-tron', 'SUV'],
            ]],

            'BMW' => ['pais' => 'Alemania', 'modelos' => [
                ['Serie 2', 'Sedán'],
                ['Serie 3', 'Sedán'],
                ['Serie 5', 'Sedán'],
                ['Serie 1', 'Hatchback'],
                ['Serie 4', 'Coupé'],
                ['X1', 'SUV'],
                ['X3', 'SUV'],
                ['X4', 'SUV'],
                ['X5', 'SUV'],
                ['X6', 'SUV'],
                ['X7', 'SUV'],
            ]],

            'Mercedes-Benz' => ['pais' => 'Alemania', 'modelos' => [
                ['Clase A', 'Sedán'],
                ['Clase C', 'Sedán'],
                ['Clase E', 'Sedán'],
                ['CLA', 'Sedán'],
                ['GLA', 'SUV'],
                ['GLB', 'SUV'],
                ['GLC', 'SUV'],
                ['GLE', 'SUV'],
                ['GLS', 'SUV'],
                ['Vito', 'Van'],
                ['Sprinter', 'Van'],
            ]],

            'Volvo' => ['pais' => 'Suecia', 'modelos' => [
                ['S60', 'Sedán'],
                ['S90', 'Sedán'],
                ['XC40', 'SUV'],
                ['XC60', 'SUV'],
                ['XC90', 'SUV'],
                ['C40', 'SUV'],
                ['EX30', 'SUV'],
            ]],

            'Lexus' => ['pais' => 'Japón', 'modelos' => [
                ['IS', 'Sedán'],
                ['ES', 'Sedán'],
                ['UX', 'SUV'],
                ['NX', 'SUV'],
                ['RX', 'SUV'],
                ['GX', 'SUV'],
                ['LX', 'SUV'],
                ['TX', 'SUV'],
            ]],

            'Jaguar' => ['pais' => 'Reino Unido', 'modelos' => [
                ['XE', 'Sedán'],
                ['XF', 'Sedán'],
                ['E-Pace', 'SUV'],
                ['F-Pace', 'SUV'],
                ['I-Pace', 'SUV'],
                ['F-Type', 'Deportivo'],
            ]],

            'Land Rover' => ['pais' => 'Reino Unido', 'modelos' => [
                ['Defender', 'SUV'],
                ['Discovery', 'SUV'],
                ['Discovery Sport', 'SUV'],
                ['Range Rover', 'SUV'],
                ['Range Rover Sport', 'SUV'],
                ['Range Rover Evoque', 'SUV'],
                ['Range Rover Velar', 'SUV'],
            ]],

            'MINI' => ['pais' => 'Reino Unido', 'modelos' => [
                ['Cooper', 'Hatchback'],
                ['Cooper S', 'Hatchback'],
                ['Clubman', 'Hatchback'],
                ['Countryman', 'SUV'],
                ['Aceman', 'SUV'],
            ]],

            'Porsche' => ['pais' => 'Alemania', 'modelos' => [
                ['Panamera', 'Sedán'],
                ['Taycan', 'Sedán'],
                ['Macan', 'SUV'],
                ['Cayenne', 'SUV'],
                ['718 Cayman', 'Coupé'],
                ['911', 'Deportivo'],
            ]],

            // ================================================================
            // CHINAS
            // Peso creciente en el mercado mexicano. Precio de entrada bajo y
            // mucho equipamiento, pero el valor de reventa todavía no tiene
            // historia: considerarlo al fijar el valor residual del contrato.
            // Son las que más rápido cambian de alineación: revisar cada año.
            // ================================================================

            'MG' => ['pais' => 'China', 'modelos' => [
                ['MG5', 'Sedán'],
                ['MG3', 'Hatchback'],
                ['MG4', 'Hatchback'],
                ['ZS', 'SUV'],
                ['HS', 'SUV'],
                ['One', 'SUV'],
                ['Cyberster', 'Deportivo'],
            ]],

            'Chirey' => ['pais' => 'China', 'modelos' => [
                ['Arrizo 5', 'Sedán'],
                ['Arrizo 6 Pro', 'Sedán'],
                ['Tiggo 2 Pro', 'SUV'],
                ['Tiggo 4 Pro', 'SUV'],
                ['Tiggo 7 Pro', 'SUV'],
                ['Tiggo 8 Pro', 'SUV'],
                ['Tiggo 8 Pro Max', 'SUV'],
            ]],

            'Jetour' => ['pais' => 'China', 'modelos' => [
                ['Dashing', 'SUV'],
                ['X70', 'SUV'],
                ['X70 Plus', 'SUV'],
                ['X90 Plus', 'SUV'],
                ['T2', 'SUV'],
            ]],

            'BYD' => ['pais' => 'China', 'modelos' => [
                ['Han', 'Sedán'],
                ['Seal', 'Sedán'],
                ['Dolphin', 'Hatchback'],
                ['Dolphin Mini', 'Hatchback'],
                ['Yuan Pro', 'SUV'],
                ['Yuan Plus', 'SUV'],
                ['Song Pro', 'SUV'],
                ['Song Plus', 'SUV'],
                ['Tang', 'SUV'],
                ['Shark', 'Pickup'],
            ]],

            'GWM' => ['pais' => 'China', 'modelos' => [
                ['Ora 03', 'Hatchback'],
                ['Haval Jolion', 'SUV'],
                ['Haval H6', 'SUV'],
                ['Haval H6 GT', 'SUV'],
                ['Tank 300', 'SUV'],
                ['Tank 500', 'SUV'],
                ['Poer', 'Pickup'],
            ]],

            // Omoda y Jaecoo se desprendieron de Chery como marcas propias hace
            // poco: su alineación real en México todavía es de 3-4 modelos, por
            // debajo del mínimo de 5 que se pidió. Se listan sólo los que
            // existen; inventar modelos ensuciaría el catálogo.
            'Omoda' => ['pais' => 'China', 'modelos' => [
                ['Omoda 5', 'SUV'],
                ['Omoda 5 GT', 'SUV'],
                ['Omoda E5', 'SUV'],
                ['Omoda 7', 'SUV'],
            ]],

            'Jaecoo' => ['pais' => 'China', 'modelos' => [
                ['Jaecoo 5', 'SUV'],
                ['Jaecoo 7', 'SUV'],
                ['Jaecoo 8', 'SUV'],
            ]],

            'Changan' => ['pais' => 'China', 'modelos' => [
                ['Alsvin', 'Sedán'],
                ['Eado', 'Sedán'],
                ['CS15', 'SUV'],
                ['CS35 Plus', 'SUV'],
                ['CS55 Plus', 'SUV'],
                ['CS75 Plus', 'SUV'],
                ['CS95', 'SUV'],
                ['UNI-K', 'SUV'],
                ['Hunter', 'Pickup'],
            ]],

            'JAC' => ['pais' => 'China', 'modelos' => [
                ['E10X', 'Hatchback'],
                ['Sei2', 'SUV'],
                ['Sei3', 'SUV'],
                ['Sei4', 'SUV'],
                ['Sei7', 'SUV'],
                ['Frison T6', 'Pickup'],
                ['Frison T8', 'Pickup'],
                ['Sunray', 'Van'],
            ]],

            // ================================================================
            // OTRAS
            // Presencia menor o nicho, pero aparecen en flotillas específicas
            // (RAM y GMC en obra y construcción, Jeep en zonas rurales).
            // ================================================================

            'Jeep' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['Renegade', 'SUV'],
                ['Compass', 'SUV'],
                ['Cherokee', 'SUV'],
                ['Grand Cherokee', 'SUV'],
                ['Wrangler', 'SUV'],
                ['Wagoneer', 'SUV'],
                ['Gladiator', 'Pickup'],
            ]],

            'RAM' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['RAM 700', 'Pickup'],
                ['RAM 1200', 'Pickup'],
                ['RAM 1500', 'Pickup'],
                ['RAM 2500', 'Pickup'],
                ['RAM 3500', 'Pickup'],
                ['RAM 4000', 'Pickup'],
                ['Rampage', 'Pickup'],
                ['ProMaster', 'Van'],
            ]],

            'Dodge' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['Attitude', 'Sedán'],
                ['Charger', 'Sedán'],
                ['Hornet', 'SUV'],
                ['Durango', 'SUV'],
                ['Journey', 'SUV'],
                ['Challenger', 'Deportivo'],
            ]],

            'Fiat' => ['pais' => 'Italia', 'modelos' => [
                ['Cronos', 'Sedán'],
                ['Mobi', 'Hatchback'],
                ['Argo', 'Hatchback'],
                ['Pulse', 'SUV'],
                ['Fastback', 'SUV'],
                ['Ducato', 'Van'],
            ]],

            'Peugeot' => ['pais' => 'Francia', 'modelos' => [
                ['301', 'Sedán'],
                ['208', 'Hatchback'],
                ['2008', 'SUV'],
                ['3008', 'SUV'],
                ['5008', 'SUV'],
                ['Landtrek', 'Pickup'],
                ['Partner', 'Van'],
                ['Expert', 'Van'],
            ]],

            'Mitsubishi' => ['pais' => 'Japón', 'modelos' => [
                ['Mirage G4', 'Sedán'],
                ['Mirage', 'Hatchback'],
                ['ASX', 'SUV'],
                ['Eclipse Cross', 'SUV'],
                ['Outlander', 'SUV'],
                ['Montero Sport', 'SUV'],
                ['L200', 'Pickup'],
                ['Xpander', 'Van'],
            ]],

            'Subaru' => ['pais' => 'Japón', 'modelos' => [
                ['Legacy', 'Sedán'],
                ['WRX', 'Sedán'],
                ['Impreza', 'Hatchback'],
                ['Crosstrek', 'SUV'],
                ['Forester', 'SUV'],
                ['Outback', 'SUV'],
                ['BRZ', 'Deportivo'],
            ]],

            'GMC' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['Terrain', 'SUV'],
                ['Acadia', 'SUV'],
                ['Yukon', 'SUV'],
                ['Canyon', 'Pickup'],
                ['Sierra', 'Pickup'],
                ['Hummer EV', 'Pickup'],
                ['Savana', 'Van'],
            ]],

            // Chrysler y Buick tienen alineaciones muy cortas en México
            // (2 y 4 modelos). Mismo criterio que Omoda/Jaecoo: se listan
            // los reales aunque no lleguen al mínimo de 5.
            'Chrysler' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['300', 'Sedán'],
                ['Pacifica', 'Van'],
            ]],

            'Buick' => ['pais' => 'Estados Unidos', 'modelos' => [
                ['Envista', 'SUV'],
                ['Encore GX', 'SUV'],
                ['Envision', 'SUV'],
                ['Enclave', 'SUV'],
            ]],
        ];
    }
}
