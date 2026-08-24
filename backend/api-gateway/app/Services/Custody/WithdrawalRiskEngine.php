<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Models\User;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;

class WithdrawalRiskEngine
{
    public function evaluate(User $user, string $asset, string $network, string $amount, string $destinationAddress, array $context = []): array
    {
        $decision = 'REQUIRE_2FA';
        $reasons = [];

        $threshold = (string) config('custody.signing.large_withdrawal_threshold', '10000');
        if (FinancialDecimal::compare($amount, $threshold) >= 0) {
            $decision = 'REQUIRE_REVIEW';
            $reasons[] = 'large_withdrawal';
        }

        $recent = DB::table('custody_withdrawals')
            ->where('user_id', $user->id)
            ->where('destination_address', $destinationAddress)
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
        if (!$recent) {
            $reasons[] = 'new_destination_address';
        }

        if (($context['account_restricted'] ?? false) === true) {
            return ['decision' => 'REJECT', 'reasons' => ['account_restricted']];
        }

        return ['decision' => $decision, 'reasons' => $reasons, 'requires_2fa' => in_array($decision, ['REQUIRE_2FA', 'REQUIRE_REVIEW'], true)];
    }
}
