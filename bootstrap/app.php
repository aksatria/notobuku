<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\RoleAnyMiddleware;
use App\Http\Middleware\EnsureMemberRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        /*
        |--------------------------------------------------------------------------
        | Route Middleware Aliases (Laravel 11)
        |--------------------------------------------------------------------------
        | Tambahkan semua alias middleware yang dipakai di routes/web.php
        */
        $middleware->alias([
            'role'        => RoleMiddleware::class,
            'role.any'    => RoleAnyMiddleware::class,
            'role.member' => EnsureMemberRole::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
