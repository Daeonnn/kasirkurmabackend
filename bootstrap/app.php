<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum middleware untuk API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Alias middleware
        $middleware->alias([
            'checkrole' => \App\Http\Middleware\CheckRole::class,  // Ubah dari 'check.role' ke 'checkrole'
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'kasir' => \App\Http\Middleware\KasirOnly::class,
        ]);

        // Hapus HandleCors manual, Laravel sudah handle otomatis
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
