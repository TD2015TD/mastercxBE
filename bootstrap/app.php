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
        // Trust all upstream proxies (CloudFlare, load balancers) so that
        // $request->getScheme() reflects X-Forwarded-Proto: https
        // instead of the raw HTTP connection nginx sees on port 80.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'jwt.verify' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,
            'jwt.auto.refresh' => \App\Http\Middleware\AutoRefreshJwtToken::class,
            'citizen.session' => \App\Http\Middleware\ValidateCitizenSession::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'permission.any' => \App\Http\Middleware\CheckAnyPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
