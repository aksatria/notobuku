<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\RoleAnyMiddleware;
use App\Http\Middleware\EnsureMemberRole;
use App\Http\Middleware\TrackCirculationMetrics;
use App\Http\Middleware\TrackOpacMetrics;
use App\Http\Middleware\OpacConditionalGet;
use App\Http\Middleware\RequestTraceMiddleware;
use App\Http\Middleware\OpacQueryGuard;

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
            'track.circulation.metrics' => TrackCirculationMetrics::class,
            'track.opac.metrics' => TrackOpacMetrics::class,
            'opac.conditional' => OpacConditionalGet::class,
            'trace.request' => RequestTraceMiddleware::class,
            'opac.query_guard' => OpacQueryGuard::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
