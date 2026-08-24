<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;

class FiatWithdrawalRiskEngine
{
    public function evaluate(int $userId, string $currency, string $amount, array $context = []): array
    {
        $threshold = (string) config('fiat.risk.large_withdrawal_threshold', '1000000');
        $decision = FinancialDecimal::compare($amount, $threshold) >= 0 ? 'REVIEW' : 'APPROVED';

        return [
            'decision' => $decision,
            'requires_manual_review' => $decision === 'REVIEW',
            'signals' => [
                'large_withdrawal' => $decision === 'REVIEW',
                'currency' => strtoupper($currency),
                'user_id' => $userId,
            ],
            'context' => $context,
        ];
    }
}
