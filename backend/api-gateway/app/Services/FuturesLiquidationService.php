<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;
use App\Models\FuturesLiquidationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class FuturesLiquidationService
{
    private const SCALE = 8;

    public function __construct(
        private readonly FuturesInsuranceFundService $insuranceFund,
        private readonly FuturesPositionService $positions,
        private readonly FuturesAdlService $adl,
    ) {
    }

    public function processOpenPositions(): int
    {
        $count = 0;

        FuturesPosition::query()
            ->where('status', 'open')
            ->orderBy('id')
            ->chunkById(100, function ($positions) use (&$count): void {
                foreach ($positions as $position) {
                    if ($this->shouldLiquidate($position)) {
                        $this->liquidate($position);
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function shouldLiquidate(FuturesPosition $position): bool
    {
        $marginCheck = $this->compare($this->effectiveMarginForLiquidation($position), (string) $position->maintenance_margin) <= 0;

        return $marginCheck
            || $this->compare((string) $position->mark_price, (string) $position->liquidation_price) <= 0 && $position->side === 'long'
            || $this->compare((string) $position->mark_price, (string) $position->liquidation_price) >= 0 && $position->side === 'short';
    }

    public function liquidate(FuturesPosition $position): FuturesPosition
    {
        return DB::transaction(function () use ($position): FuturesPosition {
            $locked = FuturesPosition::query()->lockForUpdate()->findOrFail($position->id);
            if ($locked->status !== 'open') {
                return $locked;
            }

            return $this->partialLiquidationLadder($locked);
        });
    }

    public function partialLiquidationLadder(FuturesPosition $position): FuturesPosition
    {
        $locked = FuturesPosition::query()->lockForUpdate()->findOrFail($position->id);
        if ($locked->status !== 'open') {
            return $locked;
        }

        $ratio = (string) config('futures.liquidation.partial_liquidation_ratio', '0.50');
        $stages = max(1, (int) config('futures.liquidation.max_stages', 4));

        for ($stage = 1; $stage <= $stages; $stage++) {
            $locked = $this->positions->refreshUnrealizedPnl($locked->fresh(), (string) $locked->mark_price)->fresh();
            if (!$this->shouldLiquidate($locked)) {
                return $locked;
            }

            $remainingQty = (string) $locked->quantity;
            $reduceQty = $stage === $stages
                ? $remainingQty
                : FinancialDecimal::max(FinancialDecimal::mul($remainingQty, $ratio), '0.00000001');

            if (FinancialDecimal::compare($reduceQty, $remainingQty) > 0) {
                $reduceQty = $remainingQty;
            }

            $this->executeLiquidationStage($locked, $reduceQty, $stage);
            $locked = $locked->fresh();

            if ($locked->status !== 'open') {
                return $locked;
            }
        }

        return $locked->fresh();
    }

    private function executeLiquidationStage(FuturesPosition $locked, string $reduceQty, int $stage): void
    {
            $liquidationId = (string) Str::uuid();
            $notional = $this->mul((string) $locked->mark_price, (string) $locked->quantity);
            $stageNotional = $this->mul((string) $locked->mark_price, $reduceQty);
            $fee = $this->mul($stageNotional, (string) config('futures.liquidation.fee_rate', '0.005'));
            $reference = 'futures-liquidation-fee:' . $liquidationId;
            if ($this->compare($fee, '0') > 0) {
                $this->insuranceFund->credit('USDT', $fee, $reference, [
                    'position_id' => $locked->id,
                    'symbol' => $locked->symbol,
                    'liquidation_id' => $liquidationId,
                ]);
            }

            FuturesLiquidationEvent::query()->create([
                'liquidation_id' => $liquidationId,
                'user_id' => $locked->user_id,
                'futures_position_id' => $locked->id,
                'futures_market_id' => $locked->futures_market_id,
                'symbol' => $locked->symbol,
                'mark_price' => (string) $locked->mark_price,
                'liquidation_price' => (string) $locked->liquidation_price,
                'quantity' => $reduceQty,
                'liquidation_fee' => $fee,
                'insurance_impact' => $fee,
                'status' => FinancialDecimal::compare($reduceQty, (string) $locked->quantity) < 0 ? 'partially_executed' : 'completed',
                'ledger_reference' => $reference,
                'metadata' => [
                    'policy' => 'phase5b_partial_liquidation_ladder',
                    'stage' => $stage,
                    'requested_qty' => $reduceQty,
                    'executed_qty' => $reduceQty,
                    'bankruptcy_price' => (string) ($locked->bankruptcy_price ?? '0'),
                    'notional_before' => $notional,
                ],
            ]);

            $proportion = FinancialDecimal::div($reduceQty, (string) $locked->quantity);
            $realized = FinancialDecimal::mul((string) $locked->unrealized_pnl, $proportion);
            $marginRelease = FinancialDecimal::mul((string) $locked->margin, $proportion);
            $locked->realized_pnl = $this->add((string) $locked->realized_pnl, $realized);
            $locked->quantity = FinancialDecimal::sub((string) $locked->quantity, $reduceQty);
            $locked->margin = FinancialDecimal::sub((string) $locked->margin, $marginRelease);
            $locked->isolated_margin = FinancialDecimal::sub((string) ($locked->isolated_margin ?? '0'), FinancialDecimal::min((string) ($locked->isolated_margin ?? '0'), $marginRelease));
            if (FinancialDecimal::compare((string) $locked->quantity, '0') <= 0) {
                $locked->status = 'liquidated';
                $locked->quantity = '0.00000000';
                $locked->margin = '0.00000000';
                $locked->isolated_margin = '0.00000000';
            }
            $locked->save();

            try {
                Redis::publish((string) config('futures.stream_channel', 'futures_updates'), json_encode([
                    'event' => 'futures.liquidation',
                    'data' => ['position_id' => $locked->id, 'user_id' => $locked->user_id, 'symbol' => $locked->symbol],
                    'timestamp' => now()->toISOString(),
                ], JSON_THROW_ON_ERROR));
            } catch (\Throwable) {
            }
    }

    public function handleBankruptcyDeficit(FuturesPosition $position, string $deficit): array
    {
        if ($this->compare($deficit, '0') <= 0) {
            return ['insurance_covered' => '0', 'adl_triggered' => false];
        }

        $reference = 'futures-bankruptcy-deficit:' . $position->id . ':' . md5($deficit);
        try {
            $this->insuranceFund->coverDeficitOrFail('USDT', $deficit, $reference, ['position_id' => $position->id, 'symbol' => $position->symbol]);
            return ['insurance_covered' => $deficit, 'adl_triggered' => false];
        } catch (\RuntimeException) {
            $opposingSide = $position->side === 'long' ? 'short' : 'long';
            $queue = $this->adl->rankQueue((string) $position->symbol, $opposingSide);
            if ($queue !== []) {
                $top = $queue[0];
                $this->adl->queueEvent($top['position'], FinancialDecimal::min((string) $top['position']->quantity, (string) $position->quantity), (string) $top['rank_score']);
            }

            return ['insurance_covered' => '0', 'adl_triggered' => $queue !== []];
        }
    }

    private function add(string $a, string $b): string { return FinancialDecimal::add($a, $b, self::SCALE); }
    private function mul(string $a, string $b): string { return FinancialDecimal::mul($a, $b, self::SCALE); }
    private function compare(string $a, string $b): int { return FinancialDecimal::compare($a, $b, self::SCALE); }

    private function effectiveMarginForLiquidation(FuturesPosition $position): string
    {
        if (($position->margin_type ?? 'cross') !== 'cross') {
            return (string) $position->margin;
        }

        return (string) $position->margin;
    }
}
