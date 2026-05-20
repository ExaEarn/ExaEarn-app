<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Jobs\ExecuteSwapJob;
use App\Models\Quote;
use App\Models\Swap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SwapEngineService
{
    public function __construct(
        private readonly FxRateService $fxRateService,
        private readonly CryptoLiquidityService $cryptoLiquidityService,
        private readonly WalletService $walletService,
        private readonly TransactionService $transactionService,
    ) {
    }

    public function createQuote(int $userId, string $fromCurrency, string $toCurrency, string $amount): Quote
    {
        [$routeType, $route] = $this->resolveRoute($fromCurrency, $toCurrency);
        $rate = $this->resolveRate($fromCurrency, $toCurrency, $routeType);

        $fee = $this->calculateFee($amount);
        $netAmount = $this->sub($amount, $fee);
        $receive = $this->mul($netAmount, $rate);

        return Quote::create([
            'quote_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'from_currency' => strtoupper($fromCurrency),
            'to_currency' => strtoupper($toCurrency),
            'amount_sent' => $amount,
            'amount_received' => $receive,
            'rate' => $rate,
            'fee' => $fee,
            'route' => $route,
            'expires_at' => now()->addSeconds((int) config('swap.quote_ttl_seconds', 20)),
            'metadata' => ['route_type' => $routeType],
        ]);
    }

    public function execute(int $userId, string $quoteId, ?string $idempotencyKey = null): Swap
    {
        $swap = $this->queueExecution($userId, $quoteId, $idempotencyKey);
        if ($this->markDispatched($swap->id)) {
            ExecuteSwapJob::dispatch($swap->id)->onQueue('swaps');
        }

        return $swap;
    }

    public function queueExecution(int $userId, string $quoteId, ?string $idempotencyKey = null): Swap
    {
        return DB::transaction(function () use ($userId, $quoteId, $idempotencyKey): Swap {
            if ($idempotencyKey) {
                $existing = Swap::query()->where('user_id', $userId)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $quote = Quote::query()->where('quote_id', $quoteId)->where('user_id', $userId)->lockForUpdate()->first();
            if (!$quote) {
                throw new RuntimeException('Quote not found.');
            }

            if ($quote->consumed_at !== null) {
                throw new RuntimeException('Quote already consumed.');
            }

            if ($quote->expires_at->isPast()) {
                throw new RuntimeException('Quote expired.');
            }

            $wallet = $this->walletService->getWallet($userId, $quote->from_currency);
            if ($this->compare((string) $wallet->available_balance, (string) $quote->amount_sent) < 0) {
                throw new RuntimeException('Insufficient balance.');
            }

            $swap = Swap::create([
                'swap_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'quote_id' => $quote->quote_id,
                'from_currency' => $quote->from_currency,
                'to_currency' => $quote->to_currency,
                'amount_sent' => $quote->amount_sent,
                'amount_received' => $quote->amount_received,
                'rate' => $quote->rate,
                'fee' => $quote->fee,
                'status' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'metadata' => $quote->metadata,
            ]);

            $quote->consumed_at = now();
            $quote->save();

            return $swap->fresh();
        });
    }

    public function executeQueuedSwap(int $swapId): Swap
    {
        return DB::transaction(function () use ($swapId): Swap {
            $swap = Swap::query()->whereKey($swapId)->lockForUpdate()->firstOrFail();
            if ($swap->status === 'completed') {
                return $swap;
            }
            if ($swap->status === 'failed') {
                throw new RuntimeException('Swap already failed.');
            }

            $swap->status = 'processing';
            $swap->save();

            $debitTx = $this->transactionService->createTransaction(
                $swap->user_id,
                TransactionType::Swap,
                $swap->from_currency,
                (string) $swap->amount_sent,
                $swap->swap_id,
                ['purpose' => 'swap_debit', 'quote_id' => $swap->quote_id]
            );
            $this->walletService->freezeFromTransaction($debitTx);

            try {
                $quoteSnapshot = new Quote([
                    'from_currency' => $swap->from_currency,
                    'to_currency' => $swap->to_currency,
                    'amount_received' => $swap->amount_received,
                ]);
                if ($this->isCrypto($swap->from_currency) || $this->isCrypto($swap->to_currency)) {
                    $this->simulateOrExecuteLiquidityOrder($quoteSnapshot);
                }

                $this->walletService->settleFrozenFromTransaction($debitTx);

                $creditTx = $this->transactionService->createTransaction(
                    $swap->user_id,
                    TransactionType::Swap,
                    $swap->to_currency,
                    (string) $swap->amount_received,
                    $swap->swap_id,
                    ['purpose' => 'swap_credit', 'quote_id' => $swap->quote_id]
                );
                $this->walletService->creditFromTransaction($creditTx);

                $this->transactionService->confirmTransaction($debitTx, ['swap_id' => $swap->swap_id]);
                $this->transactionService->confirmTransaction($creditTx, ['swap_id' => $swap->swap_id]);

                $swap->status = 'completed';
                $swap->save();
            } catch (\Throwable $e) {
                $this->walletService->unfreezeFromTransaction($debitTx);
                $this->transactionService->failTransaction($debitTx, $e->getMessage());
                $swap->status = 'failed';
                $swap->failure_reason = $e->getMessage();
                $swap->save();
            }

            return $swap->fresh();
        });
    }

    private function resolveRoute(string $fromCurrency, string $toCurrency): array
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);
        $fromFiat = $this->isFiat($fromCurrency);
        $toFiat = $this->isFiat($toCurrency);

        if ($fromFiat && !$toFiat) {
            return ['fiat_to_crypto', "{$fromCurrency}->USD->{$toCurrency}"];
        }

        if (!$fromFiat && $toFiat) {
            return ['crypto_to_fiat', "{$fromCurrency}->USD->{$toCurrency}"];
        }

        if (!$fromFiat && !$toFiat) {
            return ['crypto_to_crypto', "{$fromCurrency}->USDT->{$toCurrency}"];
        }

        return ['fiat_to_fiat', "{$fromCurrency}->{$toCurrency}"];
    }

    private function resolveRate(string $fromCurrency, string $toCurrency, string $routeType): string
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        return match ($routeType) {
            'fiat_to_fiat' => $this->fxRateService->getRate($fromCurrency, $toCurrency),
            'fiat_to_crypto' => $this->div(
                $this->fxRateService->getRate($fromCurrency, 'USD'),
                $this->cryptoLiquidityService->getPrice($toCurrency . 'USDT'),
            ),
            'crypto_to_fiat' => $this->mul(
                $this->cryptoLiquidityService->getPrice($fromCurrency . 'USDT'),
                $this->fxRateService->getRate('USD', $toCurrency),
            ),
            default => $this->div(
                $this->cryptoLiquidityService->getPrice($fromCurrency . 'USDT'),
                $this->cryptoLiquidityService->getPrice($toCurrency . 'USDT'),
            ),
        };
    }

    private function simulateOrExecuteLiquidityOrder(Quote $quote): void
    {
        if ((bool) config('services.binance.simulate', true)) {
            return;
        }

        $this->cryptoLiquidityService->placeOrder([
            'symbol' => strtoupper($quote->to_currency) . 'USDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => (string) $quote->amount_received,
            'timestamp' => now()->valueOf(),
        ]);
    }

    private function calculateFee(string $amount): string
    {
        $pct = (string) config('swap.fee_percent', '0.5');
        return $this->mul($amount, $this->div($pct, '100'));
    }

    private function isFiat(string $currency): bool
    {
        return in_array(strtoupper($currency), config('swap.supported_fiat', []), true);
    }

    private function isCrypto(string $currency): bool
    {
        return in_array(strtoupper($currency), config('swap.supported_crypto', []), true);
    }

    private function mul(string $a, string $b): string
    {
        return function_exists('bcmul') ? bcmul($a, $b, 8) : number_format(((float) $a * (float) $b), 8, '.', '');
    }

    private function div(string $a, string $b): string
    {
        return function_exists('bcdiv') ? bcdiv($a, $b, 8) : number_format(((float) $a / (float) $b), 8, '.', '');
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 8) : number_format(((float) $a - (float) $b), 8, '.', '');
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 8);
        }
        $fa = (float) $a;
        $fb = (float) $b;
        return $fa < $fb ? -1 : ($fa > $fb ? 1 : 0);
    }

    private function markDispatched(int $swapId): bool
    {
        return DB::transaction(function () use ($swapId): bool {
            $swap = Swap::query()->whereKey($swapId)->lockForUpdate()->first();
            if (!$swap) {
                return false;
            }

            $alreadyDispatched = (bool) data_get($swap->metadata, 'execution_dispatched', false);
            if ($alreadyDispatched) {
                return false;
            }

            $swap->metadata = array_merge($swap->metadata ?? [], [
                'execution_dispatched' => true,
                'execution_dispatched_at' => now()->toISOString(),
            ]);
            $swap->save();

            return true;
        });
    }
}
