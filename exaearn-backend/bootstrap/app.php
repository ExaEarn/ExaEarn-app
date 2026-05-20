<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'dev.auth' => \App\Http\Middleware\DevAuthBypass::class,
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'security.layer' => \App\Http\Middleware\SecurityMiddleware::class,
            'admin.security' => \App\Http\Middleware\AdminSecurityLayer::class,
            'admin.audit' => \App\Http\Middleware\AdminActionAuditMiddleware::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'check.permission' => \App\Http\Middleware\CheckPermission::class,
            '2fa' => \App\Http\Middleware\Verify2FA::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'log.activity' => \App\Http\Middleware\LogUserActivity::class,
            'node.webhook' => \App\Http\Middleware\VerifyNodeWebhook::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Solvency checks every minute
        $schedule->command('solvency:check')
            ->everyMinute()
            ->withoutOverlapping();

        // Liquidity monitoring every 5 minutes
        $schedule->job(\App\Jobs\MonitorLiquidity::class)
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Portfolio oracle refresh every minute
        $schedule->job(\App\Jobs\RefreshPortfolioOracle::class)
            ->everyMinute()
            ->withoutOverlapping();

        // AI intelligence optimization feedback loop
        $schedule->job(\App\Jobs\AIOptimizationLoopJob::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(\App\Jobs\MarketMakerLoopJob::class)
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
