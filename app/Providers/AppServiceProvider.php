<?php

namespace App\Providers;

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Formato numérico mexicano, SIN tocar el idioma.
         *
         * `app.locale` se queda en 'es' porque de él dependen las
         * traducciones de Filament, que sólo trae 'es'. Ponerlo en 'es_MX'
         * arreglaría los montos pero dejaría el panel en inglés, porque
         * caería al fallback.
         *
         * Number::useLocale() sólo gobierna el formato de números y moneda,
         * que es justo lo que estaba mal: los montos salían como
         * "1.234,50 MXN" (formato europeo) en vez de "$1,234.50".
         */
        Number::useLocale('es_MX');

        /**
         * Filament NO respeta Number::useLocale(): al formatear dinero cae
         * explícitamente a config('app.locale'), que aquí es 'es' y produce
         * formato europeo ("1.234,50 MXN").
         *
         * Antes de eso consulta el locale por defecto de la tabla o del
         * esquema, así que se fija ahí. Cubre tablas, formularios y fichas
         * de una sola vez, sin tener que pasar el locale en los 65 money()
         * repartidos por el proyecto.
         */
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('es_MX'));
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale('es_MX'));
    }
}
