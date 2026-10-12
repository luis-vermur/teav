<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('teav:cobranza')
    ->dailyAt('01:00')
    ->timezone('America/Mexico_City')
    ->onOneServer();

Schedule::command('teav:estatus')
    ->dailyAt('01:15')
    ->timezone('America/Mexico_City')
    ->onOneServer();
/**
 * La rentabilidad se recalcula después de la cobranza: así el cálculo ya
 * ve las penalizaciones y los estatus del día.
 */
Schedule::command('teav:rentabilidad')
    ->dailyAt('01:30')
    ->timezone('America/Mexico_City')
    ->onOneServer();

/**
 * Respaldo diario, antes de que corran los procesos que modifican datos.
 * Conserva 14 días.
 */
Schedule::command('teav:respaldar')
    ->dailyAt('00:30')
    ->timezone('America/Mexico_City')
    ->onOneServer();
