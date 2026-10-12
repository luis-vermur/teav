<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte los NULL en el valor por defecto de la columna antes de guardar.
 *
 * EL PROBLEMA QUE RESUELVE
 * En PostgreSQL, el DEFAULT de una columna sólo se aplica cuando la columna
 * se OMITE del INSERT. Filament siempre incluye todos los campos del
 * formulario, así que un campo numérico que el usuario dejó vacío viaja como
 * NULL explícito y revienta contra el NOT NULL, aunque la columna tenga
 * DEFAULT 0.
 *
 * Poner ->default(0) en el formulario no basta: el usuario puede borrar el
 * contenido del campo y volver a dejarlo en null.
 *
 * En lugar de mantener a mano una lista de columnas —que se desincroniza en
 * cuanto alguien agrega una—, se lee el esquema y se rellena solo. El
 * esquema se consulta una vez por tabla y por proceso.
 */
trait RellenaNulosConElDefault
{
    /** Defaults por tabla, memorizados para no interrogar el esquema en cada save. */
    protected static array $defaultsPorTabla = [];

    public static function bootRellenaNulosConElDefault(): void
    {
        static::saving(function (Model $modelo) {
            $defaults = static::defaultsDeLaTabla($modelo->getTable());
            $atributos = $modelo->getAttributes();

            foreach ($defaults as $columna => $valor) {
                // Sólo se toca lo que viene explícitamente en null; si la
                // columna no se envía, PostgreSQL aplica su propio default.
                if (array_key_exists($columna, $atributos) && $atributos[$columna] === null) {
                    $modelo->setAttribute($columna, $valor);
                }
            }
        });
    }

    /**
     * Columnas NOT NULL con DEFAULT, y su valor ya convertido a PHP.
     *
     * @return array<string, mixed>
     */
    protected static function defaultsDeLaTabla(string $tabla): array
    {
        if (isset(static::$defaultsPorTabla[$tabla])) {
            return static::$defaultsPorTabla[$tabla];
        }

        $defaults = [];

        foreach (Schema::getColumns($tabla) as $columna) {
            if ($columna['nullable'] || $columna['default'] === null) {
                continue;
            }

            $valor = static::interpretarDefault($columna['default'], $columna['type_name']);

            if ($valor !== null) {
                $defaults[$columna['name']] = $valor;
            }
        }

        return static::$defaultsPorTabla[$tabla] = $defaults;
    }

    /**
     * Traduce el default de PostgreSQL a un valor de PHP.
     * Ejemplos: "'0'::numeric" -> 0.0 · "0.16" -> 0.16 · "false" -> false
     *
     * Devuelve null para lo que no sea un literal simple (secuencias como
     * nextval(), funciones como now()): ahí es mejor dejar que la base
     * resuelva y no adivinar.
     */
    protected static function interpretarDefault(string $default, string $tipo): mixed
    {
        // Se descarta el casteo explícito: "'0'::numeric" -> "'0'"
        $limpio = trim(preg_replace('/::[a-z0-9_ ]+$/i', '', $default));
        $limpio = trim($limpio, "'");

        if (in_array($tipo, ['bool', 'boolean'], true)) {
            return in_array(strtolower($limpio), ['true', 't', '1'], true);
        }

        if (! is_numeric($limpio)) {
            return null;   // nextval(), now(), texto: no se toca
        }

        return match (true) {
            in_array($tipo, ['int2', 'int4', 'int8', 'smallint', 'integer', 'bigint'], true) => (int) $limpio,
            default => (float) $limpio,
        };
    }
}
