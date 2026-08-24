<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\LedgerTransaction;
use App\Models\Market;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\SpotCutoverJournal;
use App\Models\SpotSettlementOutbox;
use App\Models\Trade;
use App\Models\User;
use App\Services\BalanceProjectionService;
use App\Services\FinancialDecimal;
use App\Services\LedgerReconciliationService;
use App\Services\LedgerService;
use App\Services\ReservationService;
use App\Services\TradeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SpotCutoverService
{
    private const VALID_TRANSITIONS = [
        'LEGACY' => ['SHADOW', 'CUTOVER_PENDING'],
        'SHADOW' => ['CUTOVER_PENDING', 'LEGACY'],
        'CUTOVER_PENDING' => ['HALTED_FOR_CUTOVER', 'LEGACY'],
        'HALTED_FOR_CUTOVER' => ['INITIALIZING_NEW_ENGINE', 'ROLLBACK_PENDING'],
        'INITIALIZING_NEW_ENGINE' => ['VALIDATING', 'ROLLBACK_PENDING'],
        'VALIDATING' => ['NEW', 'ROLLBACK_PENDING'],
        'NEW' => ['ROLLBACK_PENDING', 'HALTED_FOR_ROLLBACK'],
        'ROLLBACK_PENDING' => ['HALTED_FOR_ROLLBACK'],
        'HALTED_FOR_ROLLBACK' => ['ROLLBACK_ONLY', 'LEGACY'],
        'ROLLBACK_ONLY' => ['LEGACY', 'SHADOW'],
    ];

    public function __construct(
        private readonly SpotCutoverReadinessService $readiness,
        private readonly MarketEngineLeaseService $leases,
        private readonly MatchingEngineReplayService $replay,
        private readonly OrderBookSnapshotService $snapshots,
        private readonly LedgerReconciliationService $reconciliation,
        private readonly ReservationService $reservations,
        private readonly BalanceProjectionService $balances,
        private readonly TradeService $trades,
        private readonly LedgerService $ledger,
    ) {
    }

    public function transition(Market $market, string $newState, string $reason, ?int $initiatedBy = null, array $metadata = []): SpotCutoverJournal
    {
        $newState = strtoupper($newState);

        return DB::transaction(function () use ($initiatedBy, $market, $metadata, $newState, $reason): SpotCutoverJournal {
            $locked = Market::query()->whereKey($market->id)->lockForUpdate()->firstOrFail();
            $previousState = strtoupper((string) ($locked->cutover_state ?: 'LEGACY'));
            if (!in_array($newState, self::VALID_TRANSITIONS[$previousState] ?? [], true) && $newState !== $previousState) {
                throw new RuntimeException("Invalid Spot cutover transition {$previousState} -> {$newState}.");
            }

            $previousMode = strtolower((string) ($locked->engine_mode ?: 'legacy'));
            $newMode = $this->modeForState($newState, $previousMode);
            $before = $this->marketCounts($locked);
            $lease = null;
            if (in_array($newState, ['INITIALIZING_NEW_ENGINE', 'VALIDATING', 'NEW'], true)) {
                $lease = $this->leases->acquire($locked);
            }

            $reconciliation = $this->reconciliation->run();
            $snapshot = $this->snapshots->latest($locked);

            $locked->engine_mode = $newMode;
            $locked->cutover_state = $newState;
            $locked->health_status = $this->healthForState($newState);
            $locked->trading_status = in_array($newState, ['HALTED_FOR_CUTOVER', 'HALTED_FOR_ROLLBACK', 'ROLLBACK_PENDING'], true) ? 'halted' : 'trading';
            $locked->engine_mode_updated_at = now();
            $locked->save();

            $after = $this->marketCounts($locked->fresh());

            return SpotCutoverJournal::query()->create([
                'cutover_id' => (string) Str::uuid(),
                'market_id' => $locked->id,
                'market_symbol' => $locked->symbol,
                'previous_mode' => $previousMode,
                'new_mode' => $newMode,
                'previous_state' => $previousState,
                'new_state' => $newState,
                'status' => 'completed',
                'reason' => $reason,
                'initiated_by_type' => $initiatedBy ? 'admin' : 'system',
                'initiated_by_id' => $initiatedBy,
                'started_at' => now(),
                'completed_at' => now(),
                'engine_owner' => $lease?->owner_instance_id,
                'fencing_generation' => $lease?->generation,
                'last_legacy_sequence' => $before['max_legacy_sequence'],
                'new_engine_sequence' => $after['max_sequence'],
                'snapshot_id' => $snapshot?->snapshot_id,
                'reconciliation_result' => $this->compactReconciliation($reconciliation),
                'open_orders_before' => $before['open_orders'],
                'open_orders_after' => $after['open_orders'],
                'reservations_before' => $before['reserved'],
                'reservations_after' => $after['reserved'],
                'metadata' => $metadata,
            ]);
        });
    }

    public function prepareCutover(Market $market, string $reason = 'controlled_cutover'): array
    {
        $readiness = $this->readiness->evaluate($market);
        if (!$readiness['ready']) {
            throw new RuntimeException('Market is not ready for cutover: ' . implode(', ', $readiness['blockers']));
        }

        $this->transition($market, 'CUTOVER_PENDING', $reason);
        $this->transition($market->fresh(), 'HALTED_FOR_CUTOVER', 'halt new order entry for cutover');
        $cancelled = $this->cancelLegacyOpenOrders($market->fresh());
        $this->transition($market->fresh(), 'INITIALIZING_NEW_ENGINE', 'initialize new engine authority', null, ['cancelled_legacy_orders' => $cancelled]);
        $this->replay->replay($market->fresh());
        $this->transition($market->fresh(), 'VALIDATING', 'validate new engine before promotion');

        return ['readiness' => $readiness, 'cancelled_legacy_orders' => $cancelled];
    }

    public function promote(Market $market, string $reason = 'promote new spot engine'): SpotCutoverJournal
    {
        if (strtoupper((string) $market->cutover_state) !== 'VALIDATING') {
            throw new RuntimeException('Market must be VALIDATING before promotion.');
        }

        return $this->transition($market, 'NEW', $reason);
    }

    public function rollback(Market $market, string $reason = 'controlled rollback'): array
    {
        $this->transition($market, 'ROLLBACK_PENDING', $reason);
        $this->transition($market->fresh(), 'HALTED_FOR_ROLLBACK', 'halt new engine for rollback');
        $cancelled = $this->cancelLegacyOpenOrders($market->fresh());
        $journal = $this->transition($market->fresh(), 'ROLLBACK_ONLY', 'legacy available only for explicit rollback', null, ['cancelled_new_engine_orders' => $cancelled]);

        return ['journal' => $journal, 'cancelled_orders' => $cancelled];
    }

    public function runCanary(Market $market, string $baseAmount = '1', string $price = '10'): array
    {
        if (strtolower((string) $market->engine_mode) !== SpotEngineModeResolver::NEW) {
            throw new RuntimeException('Canary requires NEW engine authority.');
        }

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $this->seedUnifiedTrading($seller, strtoupper((string) $market->base_currency), $baseAmount);
        $this->seedUnifiedTrading($buyer, strtoupper((string) $market->quote_currency), FinancialDecimal::mul($baseAmount, $price, 18));

        $sell = $this->trades->placeOrder($seller->id, $market->symbol, 'sell', 'limit', $baseAmount, $price, ['client_order_id' => 'canary-sell-' . Str::uuid()]);
        $buy = $this->trades->placeOrder($buyer->id, $market->symbol, 'buy', 'limit', $baseAmount, $price, ['client_order_id' => 'canary-buy-' . Str::uuid()]);
        $trade = $buy['trades'][0] ?? null;
        if (!$trade instanceof Trade) {
            throw new RuntimeException('Canary trade did not execute.');
        }

        $this->replay->verifyAgainstCurrentSnapshot($market->fresh());

        return [
            'seller_order' => $sell['order']->order_uuid,
            'buyer_order' => $buy['order']->order_uuid,
            'trade_uuid' => $trade->trade_uuid,
            'settlement_status' => $trade->fresh()->settlement_status,
            'ledger_transactions' => LedgerTransaction::query()->where('reference', $trade->settlement_reference)->count(),
            'outbox_status' => SpotSettlementOutbox::query()->where('reference', $trade->settlement_reference)->value('status'),
            'buyer_base_projection' => $this->balances->byUserAccountAndAsset($buyer->id, 'unified_trading', (string) $market->base_currency),
            'seller_quote_projection' => $this->balances->byUserAccountAndAsset($seller->id, 'unified_trading', (string) $market->quote_currency),
        ];
    }

    public function cancelLegacyOpenOrders(Market $market): int
    {
        $cancelled = 0;
        Order::query()
            ->where('market_id', $market->id)
            ->whereIn('status', ['open', 'partially_filled', 'accepted', 'pending_trigger'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$cancelled): void {
                foreach ($orders as $order) {
                    DB::transaction(function () use (&$cancelled, $order): void {
                        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                        if (!in_array($locked->status, ['open', 'partially_filled', 'accepted', 'pending_trigger'], true)) {
                            return;
                        }
                        $reservationId = (string) ($locked->reservation_id ?: data_get($locked->metadata, 'reservation_id', ''));
                        if ($reservationId !== '' && FinancialDecimal::compare((string) $locked->locked_amount, '0') > 0) {
                            $this->reservations->release($reservationId, null, [
                                'event' => 'spot_cutover_cancel_release',
                                'order_uuid' => $locked->order_uuid,
                            ]);
                        }
                        $locked->status = 'cancelled';
                        $locked->locked_amount = '0';
                        $locked->cancelled_at = now();
                        $locked->metadata = array_merge($locked->metadata ?? [], ['cancel_reason' => 'spot_cutover']);
                        $locked->save();
                        $cancelled++;
                    });
                }
            });

        return $cancelled;
    }

    private function seedUnifiedTrading(User $user, string $asset, string $amount): void
    {
        $this->ledger->getOrCreateAccount(null, 'treasury', $asset)->increment('balance', $amount);
        $reference = 'spot-cutover-canary-' . $user->id . '-' . $asset . '-' . Str::uuid();
        $this->ledger->fiatDeposit($user->id, $amount, $asset, $reference);
        $this->ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, $asset, $reference . '-transfer');
    }

    private function modeForState(string $state, string $previousMode): string
    {
        return match ($state) {
            'LEGACY' => 'legacy',
            'SHADOW' => 'shadow',
            'CUTOVER_PENDING', 'HALTED_FOR_CUTOVER', 'INITIALIZING_NEW_ENGINE', 'VALIDATING', 'HALTED_FOR_ROLLBACK' => 'halted',
            'NEW' => 'new',
            'ROLLBACK_PENDING' => 'halted',
            'ROLLBACK_ONLY' => 'rollback_only',
            default => $previousMode,
        };
    }

    private function healthForState(string $state): string
    {
        return match ($state) {
            'HALTED_FOR_CUTOVER', 'HALTED_FOR_ROLLBACK', 'ROLLBACK_PENDING' => 'HALTED',
            'INITIALIZING_NEW_ENGINE', 'VALIDATING' => 'RECOVERING',
            default => 'HEALTHY',
        };
    }

    private function marketCounts(Market $market): array
    {
        $openOrders = Order::query()->where('market_id', $market->id)->whereIn('status', ['open', 'partially_filled', 'accepted', 'pending_trigger'])->get();
        $reserved = '0';
        foreach ($openOrders as $order) {
            $reserved = FinancialDecimal::add($reserved, (string) $order->locked_amount, 18);
        }

        return [
            'open_orders' => $openOrders->count(),
            'reserved' => $reserved,
            'max_sequence' => (int) Order::query()->where('market_id', $market->id)->max('sequence'),
            'max_legacy_sequence' => (int) Order::query()->where('market_id', $market->id)->whereNull('sequence')->count(),
        ];
    }

    private function compactReconciliation(array $reconciliation): array
    {
        return [
            'balanced_transaction_failures' => count($reconciliation['balanced_transaction_failures'] ?? []),
            'negative_user_accounts' => count($reconciliation['negative_user_accounts'] ?? []),
            'reservation_integrity_failures' => count($reconciliation['reservation_integrity_failures'] ?? []),
            'legacy_projection_mismatches' => count($reconciliation['legacy_projection_mismatches'] ?? []),
            'duplicate_references' => count($reconciliation['duplicate_references'] ?? []),
        ];
    }
}
