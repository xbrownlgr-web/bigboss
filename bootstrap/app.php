<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\IsAdmin;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Render (et tout hébergeur avec proxy HTTPS) : sinon les liens et cookies sécurisés cassent.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => IsAdmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'login',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
