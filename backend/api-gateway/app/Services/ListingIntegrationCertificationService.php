<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\ListingApplication;
use App\Models\Market;
use App\Models\Reservation;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ListingIntegrationCertificationService
{
    public const VERSION = 'listing-certification-v2';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly TradeService $trades,
    ) {
    }

    public function fingerprint(ListingApplication $application): string
    {
        $application->loadMissing(['assetConfiguration', 'networkConfigurations', 'marketConfigurations.market']);

        return hash('sha256', json_encode([
            'version' => self::VERSION,
            'asset' => $application->assetConfiguration?->only(['asset_uid', 'blockchain_asset_id', 'symbol', 'network', 'contract_address', 'decimals', 'status']),
            'networks' => $application->networkConfigurations->map->only(['network_config_uuid', 'blockchain_network_id', 'blockchain_asset_id', 'network', 'contract_address', 'decimals', 'required_confirmations', 'finality_confirmations', 'status', 'validation_status'])->sortBy('network_config_uuid')->values()->all(),
            'markets' => $application->marketConfigurations->map(function ($configuration): array {
                $market = $configuration->market_id ? Market::query()->find($configuration->market_id) : null;
                return [
                ...$configuration->only(['market_config_uuid', 'market_id', 'symbol', 'tick_size', 'quantity_step', 'min_quantity', 'max_quantity', 'min_notional', 'maker_fee', 'taker_fee', 'status']),
                'canonical_market' => $market?->only(['symbol', 'base_currency', 'quote_currency', 'tick_size', 'quantity_step', 'min_order_size', 'max_order_size', 'min_notional', 'max_notional', 'maker_fee', 'taker_fee', 'engine_mode']),
                ];
            })->sortBy('market_config_uuid')->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function run(ListingApplication $application): array
    {
        $correlationId = (string) Str::uuid();
        $fingerprint = $this->fingerprint($application);
        $startedAt = now();

        if (! app()->environment('testing') || ! (bool) config('listing.integration_certification_execution_enabled', false)) {
            return $this->blockedResults($application, $correlationId, $fingerprint, $startedAt, 'ISOLATED_EXECUTION_DISABLED', 'Executable certification is permitted only in an approved isolated test environment.');
        }

        $market = $application->marketConfigurations->first()?->market;
        if (! $market instanceof Market) {
            return $this->blockedResults($application, $correlationId, $fingerprint, $startedAt, 'MARKET_NOT_CONFIGURED', 'A canonical market is required for executable certification.');
        }

        DB::beginTransaction();
        try {
            $market->forceFill([
                'status' => 'active',
                'trading_status' => 'TRADING',
                'engine_mode' => 'legacy',
                'cutover_state' => 'LEGACY',
                'health_status' => 'HEALTHY',
                'external_routing_enabled' => false,
            ])->save();

            $ledgerEvidence = $this->verifyLedger($application, $correlationId);
            $tradeEvidence = $this->verifyTrading($market->fresh(), $correlationId);
            $results = [
                $this->result('canonical_ledger', 'PASS', true, $application, $correlationId, $fingerprint, $startedAt, $ledgerEvidence),
                $this->result('matching_engine', 'PASS', true, $application, $correlationId, $fingerprint, $startedAt, $tradeEvidence['matching']),
                $this->result('partial_fill', 'PASS', true, $application, $correlationId, $fingerprint, $startedAt, $tradeEvidence['partial_fill']),
                $this->result('cancellation', 'PASS', true, $application, $correlationId, $fingerprint, $startedAt, $tradeEvidence['cancellation']),
                $this->result('settlement', 'PASS', true, $application, $correlationId, $fingerprint, $startedAt, $tradeEvidence['settlement']),
                $this->result('market_data', 'PASS', true, $application, $correlationId, $fingerprint, $startedAt, $tradeEvidence['market_data']),
                $this->result('developer_api', 'NOT_CONFIGURED', false, $application, $correlationId, $fingerprint, $startedAt, [], 'NETWORK_HARNESS_NOT_CONFIGURED', 'Developer API network conformance is not part of this isolated run.'),
                $this->result('websocket', 'NOT_CONFIGURED', false, $application, $correlationId, $fingerprint, $startedAt, [], 'NETWORK_HARNESS_NOT_CONFIGURED', 'WebSocket publication/reception requires the external network harness.'),
                $this->result('cache_redis', 'NOT_CONNECTED', false, $application, $correlationId, $fingerprint, $startedAt, [], 'REDIS_NOT_CERTIFIED', 'Redis is not a required initial-launch capability in this isolated database run.'),
                $this->capabilityResult('deposit', $application, $correlationId, $fingerprint, $startedAt),
                $this->capabilityResult('withdrawal', $application, $correlationId, $fingerprint, $startedAt),
            ];

            return $results;
        } catch (Throwable $exception) {
            return $this->blockedResults($application, $correlationId, $fingerprint, $startedAt, 'EXECUTION_FAILED', $exception->getMessage(), 'ERROR');
        } finally {
            DB::rollBack();
        }
    }

    private function verifyLedger(ListingApplication $application, string $correlationId): array
    {
        $asset = strtoupper((string) $application->assetConfiguration?->symbol);
        if ($asset === '' || $application->assetConfiguration?->blockchain_asset_id === null) {
            throw new RuntimeException('Canonical asset identity is unavailable.');
        }
        $left = $this->ledger->getOrCreateAccount(null, 'listing_certification_source', $asset);
        $right = $this->ledger->getOrCreateAccount(null, 'listing_certification_sink', $asset);
        $reference = 'listing-cert-ledger-'.$correlationId;
        $transaction = $this->ledger->postDoubleEntry($reference, 'Rollback-isolated Listing certification', [
            ['account_id' => $left->id, 'amount' => '-1', 'asset' => $asset],
            ['account_id' => $right->id, 'amount' => '1', 'asset' => $asset],
        ], 'listing_certification', ['source_service' => self::class, 'correlation_id' => $correlationId]);
        $duplicate = $this->ledger->postDoubleEntry($reference, 'Rollback-isolated Listing certification retry', [], 'listing_certification');
        $sum = LedgerEntry::query()->where('reference', $reference)->sum('amount');
        $imbalanceRejected = false;
        try {
            $this->ledger->postDoubleEntry($reference.'-invalid', 'Expected rejection', [
                ['account_id' => $left->id, 'amount' => '1', 'asset' => $asset],
            ], 'listing_certification');
        } catch (RuntimeException) {
            $imbalanceRejected = true;
        }
        if ($transaction->status !== 'completed' || $duplicate->id !== $transaction->id || bccomp((string) $sum, '0', 18) !== 0 || ! $imbalanceRejected) {
            throw new RuntimeException('Canonical ledger invariants were not satisfied.');
        }

        return ['canonical_asset_id' => $application->assetConfiguration->blockchain_asset_id, 'asset' => $asset, 'ledger_reference' => $reference, 'transaction_id' => $transaction->id, 'entry_count' => LedgerEntry::query()->where('reference', $reference)->count(), 'debits_equal_credits' => true, 'imbalance_rejected' => true, 'idempotent_retry' => true, 'rollback_isolated' => true];
    }

    private function verifyTrading(Market $market, string $correlationId): array
    {
        $seller = $this->testUser($correlationId, 'seller');
        $buyer = $this->testUser($correlationId, 'buyer');
        $base = strtoupper((string) $market->base_currency);
        $quote = strtoupper((string) $market->quote_currency);
        $this->fundTrading($seller, $base, '10', $correlationId.'-seller');
        $this->fundTrading($buyer, $quote, '1000000', $correlationId.'-buyer');
        $price = '10';

        $sell = $this->trades->placeOrder($seller->id, $market->symbol, 'sell', 'limit', '1', $price, ['client_order_id' => 'listing-cert-full-sell-'.$correlationId]);
        $buy = $this->trades->placeOrder($buyer->id, $market->symbol, 'buy', 'limit', '1', $price, ['client_order_id' => 'listing-cert-full-buy-'.$correlationId]);
        $trade = $buy['trades'][0] ?? null;
        if (! $trade instanceof Trade || $buy['order']->status !== 'filled' || $sell['order']->fresh()->status !== 'filled') {
            throw new RuntimeException('Canonical matcher did not produce the expected full fill.');
        }
        $settlementReference = $trade->settlement_reference ?: 'spot-fill:'.$trade->trade_uuid;
        $ledger = LedgerTransaction::query()->where('reference', $settlementReference)->first();
        if (! $ledger || $ledger->status !== 'completed') {
            throw new RuntimeException('Canonical trade settlement did not complete.');
        }

        $partialSell = $this->trades->placeOrder($seller->id, $market->symbol, 'sell', 'limit', '4', '11', ['client_order_id' => 'listing-cert-partial-sell-'.$correlationId]);
        $partialBuy = $this->trades->placeOrder($buyer->id, $market->symbol, 'buy', 'limit', '10', '11', ['client_order_id' => 'listing-cert-partial-buy-'.$correlationId]);
        $partialOrder = $partialBuy['order']->fresh();
        if ($partialOrder->status !== 'partially_filled' || bccomp((string) $partialOrder->remaining_amount, '6', 8) !== 0 || $partialSell['order']->fresh()->status !== 'filled') {
            throw new RuntimeException('Canonical matcher did not produce the expected partial fill.');
        }
        $reservation = Reservation::query()->where('reservation_id', data_get($partialOrder->metadata, 'reservation_id'))->firstOrFail();
        $cancelled = $this->trades->cancelOrder($buyer->id, $partialOrder->order_uuid)->fresh();
        if ($cancelled->status !== 'cancelled' || $reservation->fresh()->status !== Reservation::STATUS_RELEASED) {
            throw new RuntimeException('Canonical cancellation did not release the remaining reservation.');
        }
        $tradeCountBefore = Trade::query()->where('market_id', $market->id)->count();
        $this->trades->cancelOrder($buyer->id, $partialOrder->order_uuid);
        if (Trade::query()->where('market_id', $market->id)->count() !== $tradeCountBefore) {
            throw new RuntimeException('Cancelled order produced an unexpected fill.');
        }

        $recent = $this->trades->getRecentTrades($market->symbol, 20);
        $book = $this->trades->getOrderBook($market->symbol, 20);
        if (! $recent->contains('trade_uuid', $trade->trade_uuid) || ! array_key_exists('bids', $book) || ! array_key_exists('asks', $book)) {
            throw new RuntimeException('Canonical market-data read models did not expose the executed trade and order book.');
        }
        $entrySums = LedgerEntry::query()->where('reference', $settlementReference)->get()->groupBy('asset')->map(fn ($entries) => $entries->reduce(fn (string $sum, $entry): string => FinancialDecimal::add($sum, (string) $entry->amount), '0'));
        if ($entrySums->contains(fn (string $sum): bool => FinancialDecimal::compare($sum, '0') !== 0)) {
            throw new RuntimeException('Trade settlement ledger entries are not balanced by asset.');
        }

        return [
            'matching' => ['canonical_service' => TradeService::class, 'market_id' => $market->id, 'market' => $market->symbol, 'sell_order_uuid' => $sell['order']->order_uuid, 'buy_order_uuid' => $buy['order']->order_uuid, 'trade_uuid' => $trade->trade_uuid, 'observed_status' => 'FILLED', 'rollback_isolated' => true],
            'partial_fill' => ['buy_order_uuid' => $partialOrder->order_uuid, 'sell_order_uuid' => $partialSell['order']->order_uuid, 'filled_quantity' => '4', 'remaining_quantity' => '6', 'observed_status' => 'PARTIALLY_FILLED'],
            'cancellation' => ['order_uuid' => $cancelled->order_uuid, 'observed_status' => 'CANCELLED', 'reservation_status' => Reservation::STATUS_RELEASED, 'subsequent_fill_prevented' => true],
            'settlement' => ['trade_uuid' => $trade->trade_uuid, 'ledger_reference' => $settlementReference, 'ledger_transaction_id' => $ledger->id, 'settlement_status' => $ledger->status, 'balanced_by_asset' => true, 'fee_entries_observed' => LedgerEntry::query()->where('reference', $settlementReference)->count() >= 4],
            'market_data' => ['canonical_service' => TradeService::class, 'market' => $market->symbol, 'trade_observed' => true, 'order_book_observed' => true, 'observed_at' => now()->toISOString()],
        ];
    }

    private function fundTrading(User $user, string $asset, string $amount, string $suffix): void
    {
        $this->ledger->fiatDeposit($user->id, $amount, $asset, 'listing-cert-fund-'.$suffix.'-'.$asset);
        $this->ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, 'listing-cert-transfer-'.$suffix.'-'.$asset);
    }

    private function testUser(string $correlationId, string $role): User
    {
        return User::query()->create(['name' => 'Listing Certification '.$role, 'email' => "listing-cert-{$role}-{$correlationId}@invalid.example", 'unique_user_id' => 'CERT-'.Str::upper(Str::random(20)), 'password' => Str::random(64), 'email_verified_at' => now(), 'account_status' => 'active']);
    }

    private function capabilityResult(string $capability, ListingApplication $application, string $correlationId, string $fingerprint, $startedAt): array
    {
        $scheduled = $capability === 'deposit' ? $application->schedule?->deposit_open_at : $application->schedule?->withdrawal_open_at;
        return $scheduled
            ? $this->result($capability, 'BLOCKED', true, $application, $correlationId, $fingerprint, $startedAt, [], strtoupper($capability).'_CERTIFICATION_NOT_IN_SCOPE', ucfirst($capability).' verification has not executed and cannot be certified by this remediation.')
            : $this->result($capability, 'NOT_APPLICABLE', false, $application, $correlationId, $fingerprint, $startedAt, ['initial_launch_capability' => false], null, ucfirst($capability).' is excluded from this initial certification; later activation requires a separate fresh gate.');
    }

    private function blockedResults(ListingApplication $application, string $correlationId, string $fingerprint, $startedAt, string $code, string $message, string $status = 'BLOCKED'): array
    {
        return collect(['canonical_ledger', 'matching_engine', 'partial_fill', 'cancellation', 'settlement', 'market_data'])
            ->map(fn (string $name): array => $this->result($name, $status, true, $application, $correlationId, $fingerprint, $startedAt, [], $code, $message))
            ->merge([
                $this->result('developer_api', 'NOT_CONFIGURED', false, $application, $correlationId, $fingerprint, $startedAt, [], 'NETWORK_HARNESS_NOT_CONFIGURED', 'Developer API network conformance was not executed.'),
                $this->result('websocket', 'NOT_CONFIGURED', false, $application, $correlationId, $fingerprint, $startedAt, [], 'NETWORK_HARNESS_NOT_CONFIGURED', 'WebSocket network conformance was not executed.'),
                $this->result('cache_redis', 'NOT_CONNECTED', false, $application, $correlationId, $fingerprint, $startedAt, [], 'REDIS_NOT_CERTIFIED', 'Redis was not certified.'),
                $this->capabilityResult('deposit', $application, $correlationId, $fingerprint, $startedAt),
                $this->capabilityResult('withdrawal', $application, $correlationId, $fingerprint, $startedAt),
            ])->all();
    }

    private function result(string $name, string $status, bool $required, ListingApplication $application, string $correlationId, string $fingerprint, $startedAt, array $evidence = [], ?string $errorCode = null, ?string $errorMessage = null): array
    {
        if ($status === 'PASS' && $evidence === []) {
            throw new RuntimeException("PASS requires evidence for {$name}.");
        }
        return ['test_name' => $name, 'status' => $status, 'required' => $required, 'started_at' => $startedAt->toISOString(), 'completed_at' => now()->toISOString(), 'environment' => app()->environment(), 'target_asset' => $application->assetConfiguration?->symbol, 'target_network' => $application->assetConfiguration?->network, 'target_market' => $application->marketConfigurations->first()?->symbol, 'evidence' => $evidence, 'error_code' => $errorCode, 'error_message' => $errorMessage, 'correlation_id' => $correlationId, 'certification_version' => self::VERSION, 'configuration_fingerprint' => $fingerprint];
    }
}
