<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class LedgerEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_entry_enforcement_rejects_unbalanced_commit(): void
    {
        $service = app(LedgerService::class);
        $user = User::factory()->create();
        $account = $service->getOrCreateAccount($user->id, 'funding', 'NGN');

        $service->createTransaction('ref_unbalanced', 'bad tx');
        $service->addEntry($account->id, '100', 'NGN', 'ref_unbalanced', 'deposit', $user->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Double-entry check failed');
        $service->commitTransaction('ref_unbalanced');
    }

    public function test_fiat_deposit_posts_balanced_entries_and_updates_balance(): void
    {
        Redis::shouldReceive('publish')->times(2);

        $service = app(LedgerService::class);
        $user = User::factory()->create();
        $service->getOrCreateAccount(null, 'treasury', 'NGN')->update(['balance' => '1000']);

        $tx = $service->fiatDeposit($user->id, '200', 'NGN', 'ref_deposit_1');

        $this->assertSame('completed', $tx->status);
        $this->assertSame('200.000000000000000000', $service->getBalance($user->id, 'NGN'));
    }

    public function test_internal_transfer_moves_balance_between_wallets(): void
    {
        Redis::shouldReceive('publish')->times(4);

        $service = app(LedgerService::class);
        $user = User::factory()->create();

        $service->getOrCreateAccount(null, 'treasury', 'USDT')->update(['balance' => '1000']);
        $service->fiatDeposit($user->id, '300', 'USDT', 'ref_seed_usdt');

        $service->internalTransfer($user->id, 'funding', 'spot', '120', 'USDT', 'ref_shift_1');

        $funding = Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USDT')->firstOrFail();
        $spot = Account::query()->where('user_id', $user->id)->where('account_type', 'spot')->where('asset', 'USDT')->firstOrFail();

        $this->assertSame('180.000000000000000000', (string) $funding->balance);
        $this->assertSame('120.000000000000000000', (string) $spot->balance);
    }
}
