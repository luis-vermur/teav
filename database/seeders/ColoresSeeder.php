<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CATÁLOGO DE COLORES AUTOMOTRICES
 *
 * De dónde salen los datos:
 * Colores de carrocería más frecuentes en el parque vehicular mexicano.
 * El orden del arreglo NO es alfabético: va de mayor a menor frecuencia de
 * venta en México. Blanco, los grises/plata y negro concentran la mayoría
 * del mercado; los colores vivos (naranja, amarillo, bronce) son residuales.
 *
 * Como el orden del arreglo determina el orden de inserción —y por lo tanto
 * el id—, los colores más comunes quedan arriba en cualquier listado que
 * ordene por id. Eso es intencional: acelera la captura del usuario.
 *
 * El hex es APROXIMADO. Sirve para pintar el chip de color en la UI, no para
 * igualar el código de pintura de la armadora (cada marca tiene el suyo:
 * "Blanco Candy", "Gris Platino", etc.). Si el negocio necesita el código
 * exacto de la armadora, eso va en otra columna, no aquí.
 *
 * Mantenimiento: este catálogo es estable, casi no cambia con los años.
 */
class ColoresSeeder extends Seeder
{
    public function run(): void
    {
        // Ordenados por frecuencia real en el mercado mexicano, no alfabéticamente.
        $colores = [
            // ---------- Dominantes: la mayoría del parque vehicular ----------
            ['Blanco',      '#FFFFFF'],
            ['Gris Plata',  '#C0C0C0'],
            ['Negro',       '#0B0B0B'],
            ['Plata',       '#B8BCC0'],
            ['Gris Oxford', '#4A4E54'],

            // ---------- Frecuentes ----------
            ['Grafito',     '#383B40'],
            ['Rojo',        '#C41E1E'],
            ['Azul Marino', '#1B2A4A'],

            // ---------- Ocasionales ----------
            ['Beige',       '#D9C9A8'],
            ['Arena',       '#C2B280'],
            ['Vino',        '#6E1A2B'],
            ['Azul Cielo',  '#7FB2E5'],
            ['Café',        '#5B3A21'],
            ['Verde',       '#2E6B3E'],

            // ---------- Poco comunes: normalmente ediciones o deportivos ----------
            ['Dorado',      '#C9A227'],
            ['Bronce',      '#8C6239'],
            ['Naranja',     '#E2681A'],
            ['Amarillo',    '#F2C200'],
        ];

        $creados = 0;
        $existentes = 0;

        // Una sola transacción para los ~18 colores: agrupa los writes sin
        // sacrificar la idempotencia, porque cada fila sigue pasando por
        // firstOrCreate (la unicidad la garantiza el índice de `nombre`).
        DB::transaction(function () use ($colores, &$creados, &$existentes) {
            foreach ($colores as [$nombre, $hex]) {
                $color = Color::firstOrCreate(
                    ['nombre' => $nombre],   // búsqueda: respeta el único de `nombre`
                    ['hex' => $hex],         // sólo se usa si hay que crearlo
                );

                $color->wasRecentlyCreated ? $creados++ : $existentes++;
            }
        });

        $this->command->info("Colores: {$creados} creados, {$existentes} ya existían.");
    }
}
