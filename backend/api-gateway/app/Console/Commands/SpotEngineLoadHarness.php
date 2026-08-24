<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Market;
use App\Models\SpotEngineLoadRun;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Spot\MatchingEngineReplayService;
use App\Services\TradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Throwable;

class SpotEngineLoadHarness extends Command
{
    protected $signature = 'spot:load-harness {--orders=100} {--market=LOAD/USDT}';

    protected $description = 'Run a non-production Spot engine load/correctness harness.';

    public function handle(TradeService $tradeService, LedgerService $ledger, MatchingEngineReplayService $replay): int
    {
        Config::set('trading.engine.mode', 'new');
        $orders = max(2, (int) $this->option('orders'));
        $symbol = strtoupper((string) $this->option('market'));
        $market = Market::query()->firstOrCreate(['symbol' => $symbol], [
            'base_currency' => explode('/', $symbol)[0] ?? 'LOAD',
            'quote_currency' => explode('/', $symbol)[1] ?? 'USDT',
            'status' => 'active',
            'trading_status' => 'trading',
            'last_price' => '10',
            'price_precision' => '0.01',
            'tick_size' => '0.01',
            'quantity_step' => '0.0001',
            'min_order_size' => '0.0001',
            'max_order_size' => '1000000',
            'min_notional' => '0.01',
            'max_notional' => '0',
            'maker_fee' => '0.001',
            'taker_fee' => '0.002',
        ]);

        [$base, $quote] = explode('/', $market->symbol, 2);
        $seller = User::factory()->create(['email' => 'load-seller-' . Str::uuid() . '@exaearn.local']);
        $buyer = User::factory()->create(['email' => 'load-buyer-' . Str::uuid() . '@exaearn.local']);
        $this->fund($ledger, $seller, $base, '100000');
        $this->fund($ledger, $buyer, $quote, '1000000');

        $latencies = [];
        $errors = 0;
        $accepted = 0;
        $start = hrtime(true);

        for ($i = 0; $i < $orders; $i++) {
            $before = hrtime(true);
            try {
                if ($i % 2 === 0) {
                    $tradeService->placeOrder($seller->id, $market->symbol, 'sell', 'limit', '1', '10.00', ['client_order_id' => 'load-s-' . $i]);
                } else {
                    $tradeService->placeOrder($buyer->id, $market->symbol, 'buy', 'limit', '1', '10.00', ['client_order_id' => 'load-b-' . $i]);
                }
                $accepted++;
            } catch (Throwable $exception) {
                $errors++;
            }
            $latencies[] = (hrtime(true) - $before) / 1_000_000;
        }

        sort($latencies);
        $duration = (hrtime(true) - $start) / 1_000_000;
        $state = $replay->replay($market->fresh());
        $run = SpotEngineLoadRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'market_symbol' => $market->symbol,
            'orders_submitted' => $orders,
            'orders_accepted' => $accepted,
            'trades_created' => intdiv($accepted, 2),
            'duration_ms' => number_format($duration, 3, '.', ''),
            'p50_latency_ms' => number_format($this->percentile($latencies, 50), 3, '.', ''),
            'p95_latency_ms' => number_format($this->percentile($latencies, 95), 3, '.', ''),
            'p99_latency_ms' => number_format($this->percentile($latencies, 99), 3, '.', ''),
            'error_count' => $errors,
            'metadata' => ['replay_last_sequence' => $state['last_sequence'], 'checksum' => $state['checksum']],
        ]);

        $this->line(json_encode($run->fresh()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function fund(LedgerService $ledger, User $user, string $asset, string $amount): void
    {
        $ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, $asset, 'load-seed-' . $user->id . '-' . $asset);
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, 'load-transfer-' . $user->id . '-' . $asset);
    }

    private function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }
        $index = (int) floor((count($values) - 1) * ($percentile / 100));

        return (float) $values[$index];
    }
}
