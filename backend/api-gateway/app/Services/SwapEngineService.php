<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ExecuteSwapJob;
use App\Models\LedgerTransaction;
use App\Models\Quote;
use App\Models\Reservation;
use App\Models\Swap;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SwapEngineService
{
    public function __construct(
        private readonly FxRateService $fxRateService,
        private readonly CryptoLiquidityService $cryptoLiquidityService,
        private readonly SwapPricingService $pricing,
        private readonly ConvertBackingService $backing,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlements,
    ) {
    }

    public function createQuote(int $userId, string $fromCurrency, string $toCurrency, string $amount): Quote
    {
        $this->assertSupportedAsset($fromCurrency);
        $this->assertSupportedAsset($toCurrency);
        $priced = $this->pricing->price($fromCurrency, $toCurrency, $amount);
        $capacity = $this->backing->assertCapacity($toCurrency, (string) $priced['amount_received']);

        return Quote::create([
            'quote_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'from_currency' => strtoupper($fromCurrency),
            'to_currency' => strtoupper($toCurrency),
            'amount_sent' => $priced['amount_sent'],
            'amount_received' => $priced['amount_received'],
            'rate' => $priced['rate'],
            'fee' => $priced['fee'],
            'route' => $priced['route'],
            'expires_at' => now()->addSeconds((int) config('swap.quote_ttl_seconds', 20)),
            'metadata' => [
                'route_type' => $priced['route_type'],
                'price_source' => $priced['price_source'],
                'capacity' => $capacity,
                'quote_created_at' => now()->toISOString(),
                'pricing_version' => 'phase4-v1',
            ],
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

            $this->seedFundingLedgerFromLegacyWalletIfNeeded($userId, (string) $quote->from_currency);

            $swapUuid = (string) Str::uuid();
            $reservation = $this->reservations->reserveUserAccount(
                $userId, 'funding', (string) $quote->from_currency, (string) $quote->amount_sent,
                'convert', 'swap', $swapUuid, 'convert:' . ($idempotencyKey ?: $swapUuid),
                ['product' => 'convert', 'quote_id' => $quote->quote_id]
            );
            $swap = Swap::create([
                'swap_id' => $swapUuid,
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
                'metadata' => array_merge($quote->metadata ?? [], ['reservation_id' => $reservation->reservation_id]),
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

            try {
                $this->settlements->convert(
                    (string) data_get($swap->metadata, 'reservation_id'),
                    $swap->user_id,
                    (string) $swap->from_currency,
                    (string) $swap->amount_sent,
                    (string) $swap->to_currency,
                    (string) $swap->amount_received,
                    (string) $swap->from_currency,
                    (string) $swap->fee,
                    'convert:' . $swap->swap_id,
                    ['product' => 'convert', 'quote_id' => $swap->quote_id, 'swap_id' => $swap->swap_id, 'route_type' => data_get($swap->metadata, 'route_type'), 'price_source' => data_get($swap->metadata, 'price_source')]
                );

                $swap->status = 'completed';
                $swap->metadata = array_merge($swap->metadata ?? [], [
                    'settled_at' => now()->toISOString(),
                    'settlement_reference' => 'convert:' . $swap->swap_id,
                ]);
                $swap->save();
            } catch (\Throwable $e) {
                $reservationId = (string) data_get($swap->metadata, 'reservation_id', '');
                if ($reservationId !== '') {
                    $reservation = Reservation::query()->where('reservation_id', $reservationId)->first();
                    if ($reservation && in_array($reservation->status, [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED], true)) {
                        $this->reservations->release($reservationId, null, ['event' => 'convert_failure', 'reason' => $e->getMessage()]);
                    }
                }
                $swap->status = 'failed';
                $swap->failure_reason = $e->getMessage();
                $swap->save();
            }

            return $swap->fresh();
        });
    }

    private function assertSupportedAsset(string $currency): void
    {
        $asset = strtoupper($currency);
        $supported = array_merge(
            array_map('strtoupper', config('swap.supported_fiat', [])),
            array_map('strtoupper', config('swap.supported_crypto', [])),
        );

        if (!in_array($asset, $supported, true)) {
            throw new RuntimeException("Asset {$asset} is not enabled for Convert.");
        }
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

    private function seedFundingLedgerFromLegacyWalletIfNeeded(int $userId, string $asset): void
    {
        $asset = strtoupper($asset);
        $legacyWallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('currency', $asset)
            ->lockForUpdate()
            ->first();

        if (!$legacyWallet) {
            return;
        }

        $legacyAvailable = (string) $legacyWallet->available_balance;
        if ($this->compare($legacyAvailable, '0') <= 0) {
            return;
        }

        $ledger = app(LedgerService::class);
        $funding = $ledger->getOrCreateAccount($userId, 'funding', $asset);
        if ($this->compare((string) $funding->balance, $legacyAvailable) >= 0) {
            return;
        }

        $difference = $this->sub($legacyAvailable, (string) $funding->balance);
        $reference = sprintf('LEGACY-CONVERT-SEED-%d-%s', $userId, $asset);

        if (LedgerTransaction::query()->where('reference', $reference)->exists()) {
            return;
        }

        $ledger->postDoubleEntry($reference, 'Legacy wallet balance seed for canonical convert reservation', [
            ['account' => $ledger->getOrCreateAccount(null, 'legacy_wallet_migration', $asset), 'amount' => $this->sub('0', $difference), 'user_id' => null],
            ['account' => $funding, 'amount' => $difference, 'user_id' => $userId],
        ], [
            'source_service' => 'swap_engine_service',
            'migration_bridge' => true,
            'legacy_available_balance' => $legacyAvailable,
        ]);
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
            if (app()->environment('production')) {
                throw new RuntimeException('Simulated liquidity execution is prohibited in production.');
            }
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
        return FinancialDecimal::mul($a, $b, 8);
    }

    private function div(string $a, string $b): string
    {
        return FinancialDecimal::div($a, $b, 8);
    }

    private function sub(string $a, string $b): string
    {
        return FinancialDecimal::sub($a, $b, 8);
    }

    private function compare(string $a, string $b): int
    {
        return FinancialDecimal::compare($a, $b, 8);
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
