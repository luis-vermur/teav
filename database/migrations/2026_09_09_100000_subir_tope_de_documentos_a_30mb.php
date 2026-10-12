<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sube el tope de tamaño de documento de 10 MB a 30 MB.
 *
 * Los PDF escaneados de identificaciones, actas constitutivas y facturas
 * pasan de 10 MB con facilidad, sobre todo si vienen a color y sin
 * comprimir. El tope de 10 obligaba a comprimirlos antes de subirlos.
 *
 * Se cambia el default de la columna Y las filas existentes: si sólo se
 * cambiara el default, los 24 tipos ya sembrados seguirían en 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_documento', function (Blueprint $table) {
            $table->unsignedInteger('tamano_max_mb')->default(30)->change();
        });

        // Sólo los que siguen en el valor viejo: si alguien puso un tope
        // distinto a mano, se respeta.
        DB::table('tipos_documento')->where('tamano_max_mb', 10)->update(['tamano_max_mb' => 30]);
    }

    public function down(): void
    {
        Schema::table('tipos_documento', function (Blueprint $table) {
            $table->unsignedInteger('tamano_max_mb')->default(10)->change();
        });

        DB::table('tipos_documento')->where('tamano_max_mb', 30)->update(['tamano_max_mb' => 10]);
    }
};
