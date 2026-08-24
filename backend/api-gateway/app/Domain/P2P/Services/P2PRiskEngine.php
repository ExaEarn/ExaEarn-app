<?php

declare(strict_types=1);

namespace App\Domain\P2P\Services;

use App\Models\P2PDispute;
use App\Models\P2PTrade;
use App\Models\User;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class P2PRiskEngine
{
    public function evaluate(User $user, string $action, ?string $fiatAmount = null, array $context = []): array
    {
        $signals = [];
        $decision = 'allow';
        $severity = 'low';

        if (!$user->email_verified_at) {
            $signals[] = 'email_unverified';
            $severity = 'medium';
        }

        if ($fiatAmount !== null && !$user->email_verified_at) {
            $limit = (string) config('p2p.new_user_trade_limit', '100');
            if (FinancialDecimal::compare($fiatAmount, $limit, 8) > 0) {
                $decision = 'block_order';
                $signals[] = 'new_user_limit_exceeded';
                $severity = 'high';
            }
        }

        $recentCancelled = P2PTrade::query()
            ->where(function ($query) use ($user): void {
                $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
            })
            ->whereIn('status', ['cancelled', 'expired'])
            ->where('updated_at', '>=', now()->subDays(14))
            ->count();

        if ($recentCancelled >= (int) config('p2p.max_recent_cancellations', 5)) {
            $signals[] = 'high_recent_cancellation_rate';
            $decision = $decision === 'allow' ? 'require_review' : $decision;
            $severity = 'high';
        }

        $recentDisputes = P2PDispute::query()
            ->whereHas('trade', function ($query) use ($user): void {
                $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentDisputes >= (int) config('p2p.max_recent_disputes', 3)) {
            $signals[] = 'high_recent_dispute_rate';
            $decision = $decision === 'allow' ? 'require_review' : $decision;
            $severity = 'high';
        }

        DB::table('p2p_risk_events')->insert([
            'risk_event_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'trade_id' => $context['trade_id'] ?? null,
            'decision' => $decision,
            'severity' => $severity,
            'signals' => json_encode($signals, JSON_THROW_ON_ERROR),
            'metadata' => json_encode(array_merge($context, ['action' => $action]), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'decision' => $decision,
            'severity' => $severity,
            'signals' => $signals,
        ];
    }
}
