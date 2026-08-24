<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\Reservation;
use App\Models\SpotExecutionEvent;
use App\Models\SpotMarketDataEvent;
use App\Models\SpotShadowComparison;
use App\Services\FinancialDecimal;
use App\Services\LedgerReconciliationService;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SpotCutoverReadinessService
{
    public function __construct(
        private readonly InstrumentService $instruments,
        private readonly LedgerReconciliationService $reconciliation,
        private readonly MarketEngineLeaseService $leases,
        private readonly MatchingEngineReplayService $replay,
        private readonly OrderBookSnapshotService $snapshots,
        private readonly SettlementOutboxService $outbox,
    ) {
    }

    /**
     * @return array{status:string, ready:bool, blockers:array<int, string>, warnings:array<int, string>, metrics:array<string, mixed>}
     */
    public function evaluate(string|Market $market): array
    {
        $blockers = [];
        $warnings = [];
        $metrics = [];

        try {
            $market = $market instanceof Market ? $market->fresh() : $this->instruments->market($market);
        } catch (Throwable $exception) {
            return ['status' => 'NOT_READY', 'ready' => false, 'blockers' => ['market_not_found'], 'warnings' => [], 'metrics' => []];
        }

        if ((string) $market->status !== 'active') {
            $blockers[] = 'market_not_active';
        }
        if (!in_array((string) $market->trading_status, ['trading', 'halted'], true)) {
            $blockers[] = 'invalid_trading_status';
        }
        if (!in_array(strtolower((string) $market->engine_mode), ['legacy', 'shadow', 'new', 'halted', 'rollback_only'], true)) {
            $blockers[] = 'invalid_engine_mode';
        }

        foreach (['tick_size', 'quantity_step', 'min_order_size'] as $field) {
            if (FinancialDecimal::compare((string) $market->{$field}, '0') <= 0) {
                $blockers[] = "{$field}_not_configured";
            }
        }
        if (FinancialDecimal::compare((string) $market->min_notional, '0') < 0) {
            $blockers[] = 'min_notional_invalid';
        }

        if (!extension_loaded('bcmath')) {
            $blockers[] = 'bcmath_missing';
        }

        foreach (['spot_engine_sequences', 'spot_execution_events', 'spot_order_book_snapshots', 'spot_settlement_outbox', 'spot_market_engine_leases', 'spot_market_data_events', 'spot_cutover_journals'] as $table) {
            if (!Schema::hasTable($table)) {
                $blockers[] = "missing_table:{$table}";
            }
        }

        $reconciliation = $this->reconciliation->run();
        $metrics['reconciliation'] = [
            'balanced_transaction_failures' => count($reconciliation['balanced_transaction_failures'] ?? []),
            'negative_user_accounts' => count($reconciliation['negative_user_accounts'] ?? []),
            'reservation_integrity_failures' => count($reconciliation['reservation_integrity_failures'] ?? []),
            'duplicate_references' => count($reconciliation['duplicate_references'] ?? []),
        ];
        foreach ($metrics['reconciliation'] as $name => $count) {
            if ($count > 0) {
                $blockers[] = "reconciliation:{$name}";
            }
        }

        $outbox = $this->outbox->metrics();
        $metrics['settlement_outbox'] = $outbox;
        if (($outbox['settlement_outbox_failed'] ?? 0) > 0) {
            $blockers[] = 'settlement_outbox_failed';
        }
        if (($outbox['settlement_outbox_pending'] ?? 0) > (int) config('trading.engine.settlement_pending_halt_threshold', 1000)) {
            $blockers[] = 'settlement_outbox_backlog';
        }

        $openReservations = Reservation::query()
            ->where('purpose', 'spot_order')
            ->where('reference_type', 'order')
            ->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])
            ->where('metadata->market', $market->symbol)
            ->count();
        $metrics['open_spot_reservations'] = $openReservations;

        $unresolvedShadow = SpotShadowComparison::query()
            ->where('market_id', $market->id)
            ->where('classification', 'UNRESOLVED')
            ->count();
        $metrics['unresolved_shadow_comparisons'] = $unresolvedShadow;
        if ($unresolvedShadow > 0) {
            $blockers[] = 'unresolved_shadow_discrepancy';
        }

        try {
            $replay = $this->replay->replay($market);
            $metrics['replay'] = ['last_sequence' => $replay['last_sequence'], 'checksum' => $replay['checksum']];
        } catch (Throwable $exception) {
            $blockers[] = 'replay_failed';
            $metrics['replay_error'] = $exception->getMessage();
        }

        $snapshot = $this->snapshots->latest($market);
        if ($snapshot === null) {
            $warnings[] = 'no_snapshot_yet';
        } else {
            $metrics['latest_snapshot'] = ['snapshot_id' => $snapshot->snapshot_id, 'sequence' => $snapshot->last_sequence];
        }

        $latestExecution = SpotExecutionEvent::query()->where('market_id', $market->id)->max('sequence') ?? 0;
        $latestRealtime = SpotMarketDataEvent::query()->where('market_id', $market->id)->max('sequence') ?? 0;
        $metrics['latest_execution_sequence'] = (int) $latestExecution;
        $metrics['latest_realtime_sequence'] = (int) $latestRealtime;

        try {
            $lease = $this->leases->acquire($market);
            $metrics['lease'] = ['owner' => $lease->owner_instance_id, 'generation' => $lease->generation];
        } catch (Throwable $exception) {
            $blockers[] = 'lease_unavailable';
            $metrics['lease_error'] = $exception->getMessage();
        }

        return [
            'status' => $blockers === [] ? 'READY' : 'NOT_READY',
            'ready' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'metrics' => $metrics,
        ];
    }
}
