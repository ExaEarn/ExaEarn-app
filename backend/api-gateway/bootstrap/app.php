<?php

use App\Http\Middleware\AdminActionAuditMiddleware;
use App\Http\Middleware\AdminSecurityLayer;
use App\Http\Middleware\AllowPrivateNetworkCors;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\DevAuthBypass;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\LogUserActivity;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\SecurityMiddleware;
use App\Http\Middleware\Verify2FA;
use App\Http\Middleware\VerifyNodeWebhook;
use App\Jobs\ActivateStakingPositions;
use App\Jobs\AIOptimizationLoopJob;
use App\Jobs\CreateDelegationBatch;
use App\Jobs\DetectSlashingEvents;
use App\Jobs\DistributeNativeStakingRewards;
use App\Jobs\EvaluateExaAiSessionsJob;
use App\Jobs\EvaluateExaTokenBonusEligibility;
use App\Jobs\FetchNativeStakingRewards;
use App\Jobs\MarketMakerLoopJob;
use App\Jobs\MonitorDelegationConfirmation;
use App\Jobs\MonitorLiquidity;
use App\Jobs\MonitorRpcHealth;
use App\Jobs\MonitorStakeActivation;
use App\Jobs\ProcessPendingStakeRequests;
use App\Jobs\ReconcileStakingWallets;
use App\Jobs\RefreshPortfolioOracle;
use App\Jobs\ReleaseUnstakedPrincipal;
use App\Console\Commands\TickFlightGameRounds;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AllowPrivateNetworkCors::class);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/login';
        });

        $middleware->alias([
            'dev.auth' => DevAuthBypass::class,
            'role' => EnsureUserRole::class,
            'security.layer' => SecurityMiddleware::class,
            'admin.security' => AdminSecurityLayer::class,
            'admin.audit' => AdminActionAuditMiddleware::class,
            'permission' => CheckPermission::class,
            'check.permission' => CheckPermission::class,
            '2fa' => Verify2FA::class,
            'rate.limit' => RateLimitMiddleware::class,
            'log.activity' => LogUserActivity::class,
            'node.webhook' => VerifyNodeWebhook::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Solvency checks every minute
        $schedule->command('solvency:check')
            ->everyMinute()
            ->withoutOverlapping();

        // Liquidity monitoring every 5 minutes
        $schedule->job(MonitorLiquidity::class)
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Portfolio oracle refresh every minute
        $schedule->job(RefreshPortfolioOracle::class)
            ->everyMinute()
            ->withoutOverlapping();

        // AI intelligence optimization feedback loop
        $schedule->job(AIOptimizationLoopJob::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(MarketMakerLoopJob::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('staking:remove-legacy-xrp')
            ->dailyAt('02:30')
            ->withoutOverlapping();

        $schedule->job(MonitorRpcHealth::class)
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->job(ProcessPendingStakeRequests::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(CreateDelegationBatch::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(MonitorDelegationConfirmation::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(MonitorStakeActivation::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(ActivateStakingPositions::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(FetchNativeStakingRewards::class)
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(DistributeNativeStakingRewards::class)
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->job(ReleaseUnstakedPrincipal::class)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(ReconcileStakingWallets::class)
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(DetectSlashingEvents::class)
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        $schedule->job(EvaluateExaTokenBonusEligibility::class)
            ->everyTenMinutes()
            ->withoutOverlapping();

        $schedule->command(TickFlightGameRounds::class)
            ->everySecond()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Authentication required.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return null;
        });
    })->create();



