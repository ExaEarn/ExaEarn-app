<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;
use App\Models\InternalAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class FuturesLiquidationService
{
    private const SCALE = 8;

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

            $account = InternalAccount::query()
                ->where('user_id', $locked->user_id)
                ->where('account_type', 'futures_wallet')
                ->lockForUpdate()
                ->first();

            if ($account) {
                $release = min((float) $account->locked_balance, (float) $locked->margin);
                $account->locked_balance = $this->sub((string) $account->locked_balance, number_format($release, 8, '.', ''));
                $account->save();
            }

            $locked->status = 'liquidated';
            $locked->realized_pnl = $this->add((string) $locked->realized_pnl, (string) $locked->unrealized_pnl);
            $locked->margin = '0.00000000';
            $locked->quantity = '0.00000000';
            $locked->save();

            try {
                Redis::publish((string) config('futures.stream_channel', 'futures_updates'), json_encode([
                    'event' => 'futures.liquidation',
                    'data' => ['position_id' => $locked->id, 'user_id' => $locked->user_id, 'symbol' => $locked->symbol],
                    'timestamp' => now()->toISOString(),
                ], JSON_THROW_ON_ERROR));
            } catch (\Throwable) {
            }

            return $locked;
        });
    }

    private function add(string $a, string $b): string { return function_exists('bcadd') ? bcadd($a, $b, self::SCALE) : number_format((float)$a + (float)$b, self::SCALE, '.', ''); }
    private function sub(string $a, string $b): string { return function_exists('bcsub') ? bcsub($a, $b, self::SCALE) : number_format((float)$a - (float)$b, self::SCALE, '.', ''); }
    private function compare(string $a, string $b): int { return function_exists('bccomp') ? bccomp($a, $b, self::SCALE) : ((float)$a <=> (float)$b); }

    private function effectiveMarginForLiquidation(FuturesPosition $position): string
    {
        if (($position->margin_type ?? 'cross') !== 'cross') {
            return (string) $position->margin;
        }

        $account = InternalAccount::query()
            ->where('user_id', $position->user_id)
            ->where('account_type', 'futures_wallet')
            ->first();

        if (!$account) {
            return (string) $position->margin;
        }

        return $this->add((string) $position->margin, (string) $account->available_balance);
    }
}

