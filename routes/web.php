<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DescargarDocumentoController;

/**
 * La raíz es la puerta natural del sistema.
 *
 * Sin sesión, Filament manda al login; con sesión, cae directo al tablero.
 * Por eso se redirige a /admin y no a /admin/login: así quien ya entró no
 * vuelve a ver la pantalla de acceso.
 *
 * La landing pública (cteav.com) es un sitio estático aparte, no vive aquí.
 */
Route::redirect('/', '/admin');

Route::get('/documentos/{documento}', DescargarDocumentoController::class)
    ->middleware(['auth', 'signed'])
    ->name('documentos.descargar');