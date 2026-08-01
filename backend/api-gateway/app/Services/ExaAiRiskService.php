<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiSession;
use App\Models\TradingSignal;
use RuntimeException;

class ExaAiRiskService
{
    private const SCALE = 8;

    public function assertSessionCanTrade(ExaAiSession $session, TradingSignal $signal, string $assetAmount): void
    {
        if ($session->status !== 'active') {
            throw new RuntimeException('ExaAI session is not active.');
        }

        if ($session->subscription->status !== 'active') {
            throw new RuntimeException('ExaAI subscription is not active.');
        }

        if ($session->ends_at && $session->ends_at->isPast()) {
            throw new RuntimeException('ExaAI session has expired.');
        }

        if (! $signal->isValid()) {
            throw new RuntimeException('Trading signal is no longer valid.');
        }

        $constraints = $session->constraints ?? [];
        $minConfidence = (int) ($constraints['min_signal_confidence'] ?? 60);
        if ((int) $signal->confidence < $minConfidence) {
            throw new RuntimeException('Signal confidence is below strategy threshold.');
        }

        $maxDailyLoss = $this->fmt((string) ($session->max_daily_loss ?? '0'));
        if ($this->compare($maxDailyLoss, '0') > 0) {
            $todayLoss = $this->todayRealizedLoss($session);
            if ($this->compare($todayLoss, $maxDailyLoss) >= 0) {
                throw new RuntimeException('Maximum daily loss reached.');
            }
        }

        $maxDrawdownPercent = (string) ($session->max_drawdown_percent ?? '0');
        if ($this->compare($maxDrawdownPercent, '0') > 0) {
            $drawdown = $this->drawdownPercent($session);
            if ($drawdown >= (float) $maxDrawdownPercent) {
                throw new RuntimeException('Maximum drawdown reached.');
            }
        }

        if ($this->compare($this->fmt($assetAmount), '0') <= 0) {
            throw new RuntimeException('Calculated trade size is invalid.');
        }

        if ($this->compare((string) $session->allocation->available_amount, $assetAmount) < 0) {
            throw new RuntimeException('Insufficient ExaAI allocated capital.');
        }

        $openPositions = $session->orders()->whereIn('status', ['pending', 'open'])->count();
        $limit = (int) ($session->max_open_positions ?? 0);
        if ($limit > 0 && $openPositions >= $limit) {
            throw new RuntimeException('Maximum ExaAI open positions reached.');
        }
    }

    public function reserveAllocation(ExaAiSession $session, string $amount): void
    {
        $allocation = $session->allocation()->lockForUpdate()->firstOrFail();
        $amount = $this->fmt($amount);

        if ($this->compare((string) $allocation->available_amount, $amount) < 0) {
            throw new RuntimeException('Allocation available balance is insufficient.');
        }

        $allocation->available_amount = $this->sub((string) $allocation->available_amount, $amount);
        $allocation->reserved_amount = $this->add((string) $allocation->reserved_amount, $amount);
        $allocation->save();
    }

    private function todayRealizedLoss(ExaAiSession $session): string
    {
        $sum = '0';
        $rows = $session->orders()
            ->where('status', 'closed')
            ->whereDate('closed_at', now()->toDateString())
            ->get();

        foreach ($rows as $row) {
            $pnl = $this->fmt((string) $row->realized_pnl);
            if ($this->compare($pnl, '0') < 0) {
                $sum = $this->add($sum, $this->mul($pnl, '-1'));
            }
        }

        return $sum;
    }

    private function drawdownPercent(ExaAiSession $session): float
    {
        $equity = 0.0;
        $peak = 0.0;
        $drawdown = 0.0;

        foreach ($session->orders()->where('status', 'closed')->orderBy('closed_at')->get() as $order) {
            $equity += (float) $order->realized_pnl;
            $peak = max($peak, $equity);
            if ($peak > 0) {
                $drawdown = max($drawdown, (($peak - $equity) / $peak) * 100);
            }
        }

        return round($drawdown, 4);
    }

    private function fmt(string $value): string
    {
        return bcadd(trim($value), '0', self::SCALE);
    }

    private function add(string $left, string $right): string
    {
        return bcadd($left, $right, self::SCALE);
    }

    private function sub(string $left, string $right): string
    {
        return bcsub($left, $right, self::SCALE);
    }

    private function mul(string $left, string $right): string
    {
        return bcmul($left, $right, self::SCALE);
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }
}