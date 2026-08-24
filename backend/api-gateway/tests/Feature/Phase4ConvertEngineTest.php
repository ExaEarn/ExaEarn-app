<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExecuteSwapJob;
use App\Models\LedgerTransaction;
use App\Models\Market;
use App\Models\Reservation;
use App\Models\Swap;
use App\Models\TreasuryAccount;
use App\Models\TreasuryBalance;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\SwapEngineService;
use App\Services\SwapReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class Phase4ConvertEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ExecuteSwapJob::class]);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();
        Http::fake([
            'api.binance.com/api/v3/ticker/24hr*' => Http::response([
                'lastPrice' => '100000.00',
                'openPrice' => '99000.00',
                'priceChange' => '1000.00',
                'priceChangePercent' => '1.0101',
                'highPrice' => '101000.00',
                'lowPrice' => '98000.00',
                'volume' => '1.5',
                'quoteVolume' => '150000',
                'count' => 10,
            ]),
        ]);

        $this->market('BTC/USDT', 'BTC', 'USDT');
    }

    public function test_quote_uses_phase3_reference_market_data_with_explicit_source(): void
    {
        $user = User::factory()->create();
        $this->seedTreasuryBacking('BTC', '1');

        $quote = app(SwapEngineService::class)->createQuote($user->id, 'USDT', 'BTC', '1000');

        $this->assertSame('USDT', $quote->from_currency);
        $this->assertSame('BTC', $quote->to_currency);
        $this->assertSame('USDT->BTC', $quote->route);
        $this->assertSame('crypto_direct_usdt', data_get($quote->metadata, 'route_type'));
        $this->assertSame('EXTERNAL_REFERENCE', data_get($quote->metadata, 'price_source.components.0.source'));
        $this->assertSame('HEALTHY', data_get($quote->metadata, 'capacity.status'));
        $this->assertSame('0.00995000', (string) $quote->amount_received);
    }

    public function test_quote_rejects_destination_asset_when_backing_capacity_is_unavailable(): void
    {
        $user = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CONVERT_CAPACITY_UNAVAILABLE');

        app(SwapEngineService::class)->createQuote($user->id, 'USDT', 'BTC', '1000');
    }

    public function test_quote_rejects_destination_fiat_when_provider_capacity_is_unavailable(): void
    {
        Http::fake([
            'https://open.er-api.com/*' => Http::response([
                'rates' => ['USD' => '0.00066'],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CONVERT_CAPACITY_UNAVAILABLE');

        app(SwapEngineService::class)->createQuote($user->id, 'NGN', 'USD', '1000');
    }

    public function test_quote_allows_destination_fiat_when_provider_capacity_exists(): void
    {
        Http::fake([
            'https://open.er-api.com/*' => Http::response([
                'rates' => ['USD' => '0.00066'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->seedFiatTreasuryBacking('USD', '100');

        $quote = app(SwapEngineService::class)->createQuote($user->id, 'NGN', 'USD', '1000');

        $this->assertSame('NGN', $quote->from_currency);
        $this->assertSame('USD', $quote->to_currency);
        $this->assertSame('HEALTHY', data_get($quote->metadata, 'capacity.status'));
        $this->assertSame('100.000000000000000000', data_get($quote->metadata, 'capacity.available_conversion_capacity'));
    }

    public function test_execute_consumes_reservation_and_posts_idempotent_ledger_settlement(): void
    {
        $user = $this->fundFunding('USDT', '2000');
        $this->seedTreasuryBacking('BTC', '1');
        $service = app(SwapEngineService::class);
        $quote = $service->createQuote($user->id, 'USDT', 'BTC', '1000');

        $swap = $service->queueExecution($user->id, $quote->quote_id, 'convert-idem-1');
        $same = $service->queueExecution($user->id, $quote->quote_id, 'convert-idem-1');
        $this->assertSame($swap->swap_id, $same->swap_id);

        $completed = $service->executeQueuedSwap($swap->id);
        $service->executeQueuedSwap($swap->id);

        $reservation = Reservation::query()->where('reservation_id', data_get($completed->metadata, 'reservation_id'))->firstOrFail();
        $this->assertSame('completed', $completed->status);
        $this->assertSame(Reservation::STATUS_CONSUMED, $reservation->status);
        $this->assertSame(1, LedgerTransaction::query()->where('reference', 'convert:' . $swap->swap_id)->count());
        $this->assertSame('0.009950000000000000', app(LedgerService::class)->getBalance($user->id, 'BTC', 'funding'));
    }

    public function test_failed_execution_releases_reservation(): void
    {
        $user = $this->fundFunding('USDT', '2000');
        $this->seedTreasuryBacking('BTC', '1');
        $service = app(SwapEngineService::class);
        $quote = $service->createQuote($user->id, 'USDT', 'BTC', '1000');
        $swap = $service->queueExecution($user->id, $quote->quote_id, 'convert-fail-1');

        $swap->metadata = array_merge($swap->metadata ?? [], ['reservation_id' => 'missing-reservation']);
        $swap->save();

        $failed = $service->executeQueuedSwap($swap->id);

        $this->assertSame('failed', $failed->status);
        $this->assertNotEmpty($failed->failure_reason);
    }

    public function test_reconciliation_reports_pass_for_completed_swap(): void
    {
        $user = $this->fundFunding('USDT', '2000');
        $this->seedTreasuryBacking('BTC', '1');
        $service = app(SwapEngineService::class);
        $quote = $service->createQuote($user->id, 'USDT', 'BTC', '1000');
        $swap = $service->queueExecution($user->id, $quote->quote_id, 'convert-recon-1');
        $service->executeQueuedSwap($swap->id);

        $report = app(SwapReconciliationService::class)->report();

        $this->assertSame('PASS', $report['status']);
        $this->assertSame([], $report['findings']);
    }

    public function test_swap_api_quote_execute_history_and_reconciliation(): void
    {
        $user = $this->fundFunding('USDT', '2000');
        $this->seedTreasuryBacking('BTC', '1');
        $quote = $this->actingAs($user)->postJson('/api/swap/quote', [
            'from_currency' => 'USDT',
            'to_currency' => 'BTC',
            'amount' => '1000',
        ])->assertCreated()->json('data.quote_id');

        $this->actingAs($user)->withHeader('X-Idempotency-Key', 'api-convert-1')
            ->postJson('/api/swap/execute', ['quote_id' => $quote])
            ->assertAccepted();

        $this->actingAs($user)->getJson('/api/swap/history')->assertOk();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/swap/reconciliation')->assertOk();
    }

    private function market(string $symbol, string $base, string $quote): void
    {
        Market::query()->create([
            'symbol' => $symbol,
            'base_currency' => $base,
            'quote_currency' => $quote,
            'status' => 'active',
            'trading_status' => 'trading',
            'last_price' => '0',
            'price_precision' => '0.01',
            'tick_size' => '0.01',
            'quantity_step' => '0.0001',
            'min_order_size' => '0.0001',
            'max_order_size' => '100',
            'min_notional' => '10',
            'max_notional' => '0',
            'maker_fee' => '0.001',
            'taker_fee' => '0.002',
        ]);
    }

    private function fundFunding(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase4-seed-{$user->id}-{$asset}");

        return $user;
    }

    private function seedTreasuryBacking(string $asset, string $amount): void
    {
        TreasuryBalance::query()->updateOrCreate(
            ['asset' => strtoupper($asset)],
            ['balance' => $amount, 'hot_wallet_balance' => $amount, 'cold_wallet_balance' => '0']
        );
    }

    private function seedFiatTreasuryBacking(string $asset, string $amount): void
    {
        TreasuryAccount::query()->updateOrCreate(
            ['provider' => 'nomba', 'currency' => strtoupper($asset)],
            ['available_balance' => $amount, 'locked_balance' => '0', 'status' => 'active']
        );
    }
}
