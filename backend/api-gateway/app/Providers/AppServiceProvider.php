<?php

namespace App\Providers;

use App\Domain\Staking\Contracts\SecureSignerInterface;
use App\Domain\Staking\Services\HttpSecureSigner;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AutoTradingStrategy;
use App\Models\DeviceToken;
use App\Models\Giftcard;
use App\Models\Nft;
use App\Models\Notification;
use App\Models\TradingSignal;
use App\Models\User;
use App\Models\UserAsset;
use App\Models\Wallet;
use App\Policies\AutoTradingStrategyPolicy;
use App\Policies\DeviceTokenPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\TradingSignalPolicy;
use App\Services\PortfolioService;
use App\Services\RealtimeStreamService;
use App\Services\ReferralService;
use App\Services\RewardEngineService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SecureSignerInterface::class, HttpSecureSigner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(DeviceToken::class, DeviceTokenPolicy::class);
        Gate::policy(TradingSignal::class, TradingSignalPolicy::class);
        Gate::policy(AutoTradingStrategy::class, AutoTradingStrategyPolicy::class);
        Gate::define('viewAllLogs', fn ($user, string $class = ActivityLog::class): bool => $user instanceof Admin || ($user instanceof User && $user->role === 'admin'));
        Gate::define('viewAdminLogs', fn ($user, string $class = ActivityLog::class): bool => $user instanceof Admin || ($user instanceof User && $user->role === 'admin'));
        // Referral codes are safe to ensure from the user model event.
        // Wallet provisioning is handled explicitly by UserInitializationService.
        User::created(function (User $user) {
            try {
                /** @var ReferralService $referralService */
                $referralService = app(ReferralService::class);
                $referralService->ensureReferralCode($user);
            } catch (\Throwable $e) {
                Log::error('Failed to ensure referral code for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        try {
            /** @var RewardEngineService $rewardEngine */
            $rewardEngine = app(RewardEngineService::class);
            $rewardEngine->syncActivities();
        } catch (\Throwable $e) {
            Log::warning('Reward activity sync failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $invalidatePortfolio = static function ($model): void {
            try {
                $userId = $model->user_id ?? $model->owner_user_id ?? null;
                if (! $userId) {
                    return;
                }

                /** @var PortfolioService $portfolioService */
                $portfolioService = app(PortfolioService::class);
                $portfolioService->invalidateCache((int) $userId);

                try {
                    $portfolio = $portfolioService->getUserPortfolioValue((int) $userId);

                    /** @var RealtimeStreamService $streamService */
                    $streamService = app(RealtimeStreamService::class);
                    $streamService->publishPayload(config('streaming.portfolio_channel', 'portfolio_updates'), [
                        'event' => 'portfolio:update',
                        'user_id' => $userId,
                        'timestamp' => now()->toIso8601String(),
                        'data' => $portfolio,
                    ]);
                } catch (\Throwable $innerException) {
                    Log::warning('Failed to publish portfolio update', [
                        'user_id' => $userId,
                        'error' => $innerException->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Portfolio cache invalidation failed', [
                    'model' => get_class($model),
                    'error' => $e->getMessage(),
                ]);
            }
        };

        UserAsset::saved($invalidatePortfolio);
        UserAsset::deleted($invalidatePortfolio);
        Wallet::saved($invalidatePortfolio);
        Wallet::deleted($invalidatePortfolio);
        Giftcard::saved($invalidatePortfolio);
        Giftcard::deleted($invalidatePortfolio);
        Nft::saved($invalidatePortfolio);
        Nft::deleted($invalidatePortfolio);
    }
}
