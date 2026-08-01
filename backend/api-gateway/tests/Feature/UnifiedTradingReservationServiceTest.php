<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InternalAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Services\UnifiedTradingReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedTradingReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reserves_and_releases_spot_order_balance_through_shared_service(): void
    {
        $user = User::factory()->create();

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '500.00000000',
            'locked_balance' => '0.00000000',
        ]);

        $service = app(UnifiedTradingReservationService::class);
        $service->reserveSpotOrder($user->id, 'USDT', '125.00000000', 'spot-lock-1', ['source' => 'test']);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '375.00000000',
            'locked_balance' => '125.00000000',
        ]);

        $service->releaseSpotOrder($user->id, 'USDT', '25.00000000', 'spot-release-1', ['source' => 'test']);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '400.00000000',
            'locked_balance' => '100.00000000',
        ]);
    }

    public function test_it_reserves_and_releases_futures_margin_from_unified_trading_sources(): void
    {
        $user = User::factory()->create();

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '300.00000000',
            'locked_balance' => '50.00000000',
        ]);

        InternalAccount::query()->create([
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'account_name' => 'Futures Wallet',
            'available_balance' => '200.00000000',
            'locked_balance' => '75.00000000',
        ]);

        $service = app(UnifiedTradingReservationService::class);
        $allocations = $service->reserveFuturesMargin($user->id, '450.00000000', 'futures-lock-1');

        $this->assertSame([
            ['bucket' => 'spot_available', 'amount' => '300.00000000'],
            ['bucket' => 'futures_available', 'amount' => '150.00000000'],
        ], $allocations);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '0.00000000',
            'locked_balance' => '50.00000000',
        ]);

        $this->assertDatabaseHas('internal_accounts', [
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'available_balance' => '50.00000000',
            'locked_balance' => '525.00000000',
        ]);

        $service->releaseFuturesMargin($user->id, '450.00000000', 'futures-release-1', $allocations);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '300.00000000',
            'locked_balance' => '50.00000000',
        ]);

        $this->assertDatabaseHas('internal_accounts', [
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'available_balance' => '200.00000000',
            'locked_balance' => '75.00000000',
        ]);
    }

    public function test_it_reports_unified_margin_status_from_combined_sources(): void
    {
        $user = User::factory()->create();

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '150.00000000',
            'locked_balance' => '25.00000000',
        ]);

        InternalAccount::query()->create([
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'account_name' => 'Futures Wallet',
            'available_balance' => '50.00000000',
            'locked_balance' => '75.00000000',
        ]);

        $service = app(UnifiedTradingReservationService::class);
        $status = $service->getUnifiedMarginStatus($user->id);

        $this->assertSame('300.00000000', $status['total_margin']);
        $this->assertSame('200.00000000', $status['available_margin']);
        $this->assertSame('100.00000000', $status['locked_margin']);
    }

    public function test_it_rejects_futures_margin_above_unified_available_balance(): void
    {
        $this->expectExceptionMessage('Insufficient unified trading margin balance.');

        $user = User::factory()->create();

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '10.00000000',
            'locked_balance' => '0.00000000',
        ]);

        InternalAccount::query()->create([
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'account_name' => 'Futures Wallet',
            'available_balance' => '5.00000000',
            'locked_balance' => '0.00000000',
        ]);

        app(UnifiedTradingReservationService::class)->reserveFuturesMargin($user->id, '20.00000000', 'futures-lock-2');
    }
}
