<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ---------- Catálogos base ----------
        // Orden intencional: marcas depende de nada, pero submarcas cuelga de
        // marcas y se siembra dentro del mismo seeder. Todos son idempotentes
        // (firstOrCreate), así que se pueden volver a correr sin romper nada.
        $this->call([
            ColoresSeeder::class,
            MarcasSeeder::class,
            AseguradorasSeeder::class,
            TiposMantenimientoSeeder::class,
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
