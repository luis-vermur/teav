<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Confiar en el proxy de la plataforma.
         *
         * Railway termina el TLS en su borde y le entrega al contenedor una
         * petición HTTP plana, con el esquema original en X-Forwarded-Proto.
         * Sin esto Laravel cree que la petición fue http:// y genera las URLs
         * de los assets con ese esquema; el navegador las bloquea por
         * contenido mixto y el panel aparece sin estilos, como HTML pelón.
         *
         * `at: '*'` confía en cualquier proxy, que es lo correcto en Railway:
         * el contenedor no es alcanzable desde fuera más que a través de su
         * borde, así que nadie puede falsificar esas cabeceras.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
