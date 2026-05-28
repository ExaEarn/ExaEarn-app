<?php

namespace Tests\Unit;

use App\Services\GiftCard\ArbitrageDetectionService;
use App\Services\GiftCard\RateEngineService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GiftCardRateArbitrageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('giftcard_arbitrage.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_rate_engine_returns_frontend_ready_profitable_rates(): void
    {
        $rates = app(RateEngineService::class)->getRates('amazon', 100);

        $this->assertSame('amazon', $rates['brand']);
        $this->assertGreaterThan($rates['buy_rate'], $rates['sell_rate']);
        $this->assertSame(round(100 * $rates['buy_rate'], 2), $rates['payout']);
        $this->assertSame(round(100 * $rates['sell_rate'], 2), $rates['price']);
        $this->assertGreaterThan(0, $rates['platform_profit']);
        $this->assertArrayHasKey('demand_level', $rates);
        $this->assertArrayHasKey('inventory_status', $rates);
        $this->assertArrayHasKey('lock_duration', $rates);
    }

    public function test_arbitrage_detection_finds_user_resell_opportunity(): void
    {
        $opportunities = app(ArbitrageDetectionService::class)->detectArbitrage('amazon', 100);

        $this->assertNotEmpty($opportunities);
        $this->assertContains('buy_user_sell_external', array_column($opportunities, 'type'));
    }
}
