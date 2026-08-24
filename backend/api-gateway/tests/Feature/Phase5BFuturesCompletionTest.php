<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FuturesAdlEvent;
use App\Models\FuturesLiquidationEvent;
use App\Models\FuturesMarket;
use App\Models\FuturesPosition;
use App\Models\User;
use App\Services\CrossMarginHealthService;
use App\Services\FuturesAdlService;
use App\Services\FuturesInsuranceFundService;
use App\Services\FuturesLiquidationService;
use App\Services\FuturesOrderService;
use App\Services\FuturesPositionService;
use App\Services\FuturesReconciliationService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class Phase5BFuturesCompletionTest extends TestCase
{
    use RefreshDatabase;

    private FuturesMarket $btc;
    private FuturesMarket $eth;
    private FuturesMarket $sol;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('futures.allow_external_execution', false);
        Config::set('futures.liquidation.partial_liquidation_ratio', '0.50');
        Config::set('futures.liquidation.max_stages', 3);
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $this->btc = $this->market('BTCUSDT', 'BTC', '100000');
        $this->eth = $this->market('ETHUSDT', 'ETH', '5000');
        $this->sol = $this->market('SOLUSDT', 'SOL', '200');
    }

    public function test_cross_margin_health_aggregates_multi_position_equity_and_maintenance(): void
    {
        $user = $this->fundFutures('USDT', '20000');
        $this->position($user, $this->btc, 'long', '0.1', '90000', '1000', 'cross');
        $this->position($user, $this->eth, 'short', '1', '5500', '500', 'cross');
        $this->position($user, $this->sol, 'long', '10', '180', '200', 'cross');

        $health = app(CrossMarginHealthService::class)->health($user->id);

        $this->assertSame('HEALTHY', $health['risk_status']);
        $this->assertTrue(bccomp((string) $health['unrealized_pnl'], '0', 8) > 0);
        $this->assertTrue(bccomp((string) $health['maintenance_margin'], '0', 8) > 0);
        $this->assertTrue(bccomp((string) $health['available_margin'], '0', 8) > 0);
    }

    public function test_cross_pre_trade_rejects_projected_margin_failure_and_transfer_guard(): void
    {
        $user = $this->fundFutures('USDT', '1000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projected cross-margin account health is insufficient.');
        app(FuturesOrderService::class)->placeOrder($user->id, [
            'symbol' => 'BTCUSDT',
            'type' => 'limit',
            'side' => 'long',
            'price' => '95000',
            'quantity' => '1',
            'leverage' => 10,
            'margin_mode' => 'cross',
        ]);
    }

    public function test_transfer_out_guard_blocks_maintenance_breach(): void
    {
        $user = $this->fundFutures('USDT', '2000');
        $this->position($user, $this->btc, 'long', '0.1', '100000', '1000', 'cross');
        app(FuturesPositionService::class)->refreshUnrealizedPnl(FuturesPosition::query()->first(), '90000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transfer would breach futures cross-margin maintenance requirements.');
        app(CrossMarginHealthService::class)->assertCanTransferOut($user->id, 'USDT', '1500');
    }

    public function test_partial_liquidation_ladder_reduces_before_full_liquidation(): void
    {
        $user = $this->fundFutures('USDT', '5000');
        $position = $this->position($user, $this->btc, 'long', '1', '100000', '1000', 'isolated');
        app(FuturesPositionService::class)->refreshUnrealizedPnl($position, '99000');

        $result = app(FuturesLiquidationService::class)->liquidate($position->fresh());

        $this->assertContains($result->status, ['open', 'liquidated']);
        $this->assertGreaterThanOrEqual(1, FuturesLiquidationEvent::query()->where('status', 'partially_executed')->count());
        $this->assertTrue(bccomp((string) FuturesPosition::query()->find($position->id)->quantity, '1', 8) < 0);
    }

    public function test_bankruptcy_deficit_uses_insurance_then_adl_when_insufficient(): void
    {
        $user = $this->fundFutures('USDT', '10000');
        $counterparty = $this->fundFutures('USDT', '10000');
        $position = $this->position($user, $this->btc, 'long', '1', '100000', '1000', 'isolated');
        $opposing = $this->position($counterparty, $this->btc, 'short', '1', '100000', '1000', 'isolated');
        $opposing->forceFill(['mark_price' => '90000', 'unrealized_pnl' => '10000'])->save();

        app(FuturesInsuranceFundService::class)->credit('USDT', '100', 'phase5b-insurance-seed');
        $covered = app(FuturesLiquidationService::class)->handleBankruptcyDeficit($position, '50');
        $adl = app(FuturesLiquidationService::class)->handleBankruptcyDeficit($position, '500');

        $this->assertFalse($covered['adl_triggered']);
        $this->assertTrue($adl['adl_triggered']);
        $this->assertSame(1, FuturesAdlEvent::query()->where('status', 'queued')->count());
    }

    public function test_adl_execution_is_idempotent_and_partial(): void
    {
        $user = $this->fundFutures('USDT', '10000');
        $position = $this->position($user, $this->btc, 'short', '2', '100000', '2000', 'isolated');
        $position->forceFill(['mark_price' => '90000', 'unrealized_pnl' => '20000'])->save();
        $event = app(FuturesAdlService::class)->queueEvent($position, '0.5', '100');

        $first = app(FuturesAdlService::class)->executeReduction($event, '90000');
        $second = app(FuturesAdlService::class)->executeReduction($event, '90000');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('executed', $second->status);
        $this->assertSame('1.50000000', (string) $position->fresh()->quantity);
    }

    public function test_futures_reconciliation_has_zero_blocking_findings_for_valid_state(): void
    {
        $user = $this->fundFutures('USDT', '10000');
        $this->position($user, $this->btc, 'long', '0.1', '100000', '1000', 'cross');

        $result = app(FuturesReconciliationService::class)->run();

        $this->assertSame('pass', $result['status']);
        $this->assertCount(0, $result['findings']);
    }

    private function market(string $symbol, string $base, string $price): FuturesMarket
    {
        return FuturesMarket::query()->create([
            'symbol' => $symbol,
            'base_asset' => $base,
            'quote_asset' => 'USDT',
            'settlement_asset' => 'USDT',
            'contract_type' => 'PERPETUAL',
            'status' => 'active',
            'engine_mode' => 'new',
            'min_leverage' => 1,
            'max_leverage' => 100,
            'maintenance_margin_rate' => '0.005',
            'last_price' => $price,
            'index_price' => $price,
            'mark_price' => $price,
            'funding_rate' => '0',
            'tick_size' => '0.01',
            'quantity_step' => '0.0001',
            'min_quantity' => '0.0001',
            'max_quantity' => '100',
            'min_notional' => '5',
            'max_notional' => '1000000',
            'price_band_bps' => 1000,
        ]);
    }

    private function fundFutures(string $asset, string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, "phase5b-seed-{$user->id}-{$asset}");
        $ledger->internalTransfer($user->id, 'funding', 'futures', $amount, $asset, "phase5b-futures-{$user->id}-{$asset}");

        return $user;
    }

    private function position(User $user, FuturesMarket $market, string $side, string $quantity, string $entry, string $margin, string $marginType): FuturesPosition
    {
        $position = FuturesPosition::query()->create([
            'user_id' => $user->id,
            'futures_market_id' => $market->id,
            'symbol' => $market->symbol,
            'side' => $side,
            'entry_price' => $entry,
            'mark_price' => (string) $market->mark_price,
            'quantity' => $quantity,
            'leverage' => 20,
            'margin_type' => $marginType,
            'margin' => $margin,
            'isolated_margin' => $marginType === 'isolated' ? $margin : '0',
            'maintenance_margin' => '0',
            'unrealized_pnl' => '0',
            'realized_pnl' => '0',
            'accumulated_funding' => '0',
            'liquidation_price' => '0',
            'bankruptcy_price' => '0',
            'status' => 'open',
        ]);

        return app(FuturesPositionService::class)->refreshUnrealizedPnl($position, (string) $market->mark_price);
    }
}
