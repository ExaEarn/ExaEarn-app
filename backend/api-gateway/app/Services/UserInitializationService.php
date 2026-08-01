<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\UserCreated;
use App\Models\DailyCheckIn;
use App\Models\ExapointBalance;
use App\Models\InternalAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class UserInitializationService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function initializeUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            // A. MULTI-CHAIN WALLET ASSIGNMENT
            $this->walletService->provisionWalletsForUser($user);
            // $this->provisionWalletAddressesForUser($user);

            // B. CREATE INTERNAL USER ACCOUNTS (LEDGER READY)
            $this->createInternalAccounts($user);

            // D. EXAPOINT SYSTEM INITIALIZATION
            $this->initializeExapointSystem($user);

            // E. DAILY CHECK-IN SYSTEM
            $this->initializeDailyCheckIn($user);

            // F. SECURITY PREPARATION
            $this->prepareSecurity($user);

            // Emit event
            $this->emitUserCreatedEvent($user);
        });
    }

    // private function provisionWalletAddressesForUser(User $user): void
    // {
    //     foreach (config('wallet.assets', []) as $asset) {
    //         try {
    //             $this->walletService->getDepositAddress($user->id, $asset['code']);
    //         } catch (\Throwable $exception) {
    //             Log::warning('Failed to provision deposit address for user', [
    //                 'user_id' => $user->id,
    //                 'currency' => $asset['code'],
    //                 'error' => $exception->getMessage(),
    //             ]);
    //         }
    //     }
    // }

    private function createInternalAccounts(User $user): void
    {
        foreach (['funding_wallet', 'unified_trading_wallet', 'spot_wallet', 'futures_wallet', 'exapoint_account'] as $accountType) {
            InternalAccount::updateOrCreate(
                ['user_id' => $user->id, 'account_type' => $accountType],
                [
                    'account_name' => str_replace('_', ' ', ucfirst($accountType)),
                    'available_balance' => 0,
                    'locked_balance' => 0,
                ]
            );
        }

        Log::info('Internal accounts created', ['user_id' => $user->id]);
    }

    private function initializeExapointSystem(User $user): void
    {
        ExapointBalance::updateOrCreate(
            ['user_id' => $user->id],
            [
                'available_points' => 0,
                'locked_points' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
            ]
        );

        Log::info('Exapoint balance initialized', ['user_id' => $user->id]);
    }

    private function initializeDailyCheckIn(User $user): void
    {
        DailyCheckIn::updateOrCreate(
            ['user_id' => $user->id],
            [
                'last_check_in_at' => null,
                'streak' => 0,
                'next_check_in_at' => now()->addDay(),
            ]
        );

        Log::info('Daily check-in initialized', ['user_id' => $user->id]);
    }

    private function prepareSecurity(User $user): void
    {
        $user->email_verified_at = null;
        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->save();

        Log::info('Security preparation completed', ['user_id' => $user->id]);
    }

    private function emitUserCreatedEvent(User $user): void
    {
        Event::dispatch(new UserCreated($user, true));
        Log::info('User created event emitted', ['user_id' => $user->id]);
    }
}
