<?php

use App\Console\Commands\CleanupInactiveSessions;
use App\Http\Middleware\CheckFeatureActive;
use App\Http\Middleware\EnforceSessionLimit;
use App\Http\Middleware\AiTenantThrottle;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant'        => ResolveTenant::class,
            'enforce.session' => EnforceSessionLimit::class,
            'check.feature' => CheckFeatureActive::class,
            'ai.tenant.throttle' => AiTenantThrottle::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Invalida sessioni inattive da più di 8 ore — eseguito ogni ora
        $schedule->command(CleanupInactiveSessions::class)->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
